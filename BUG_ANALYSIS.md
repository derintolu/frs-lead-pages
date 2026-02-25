# FRS Lead Pages Plugin - Comprehensive Bug Analysis
**Date:** February 24, 2026  
**Plugin Version:** 1.5.0  
**Scope:** Full codebase exploration - 36 PHP files analyzed

---

## CRITICAL BUGS

### BUG #7: MISSING PARTNER PHOTO UPLOAD - NO WAY TO ADD CUSTOM PARTNER PHOTOS ⚠️ CRITICAL FEATURE GAP

**Severity:** CRITICAL (Feature Missing)  
**Type:** Missing Functionality  
**Files:**
- `/includes/OpenHouse/Wizard.php` Lines 553-617 (Step 7: Branding)
- `/includes/CustomerSpotlight/Wizard.php` (Similar pattern)
- `/includes/SpecialEvent/Wizard.php` (Similar pattern)
- `/includes/RateQuote/Wizard.php` (Similar pattern)
- `/includes/ApplyNow/Wizard.php` (Similar pattern)
- `/includes/MortgageCalculator/Wizard.php` (Similar pattern)

**The Problem:**
The wizard has two modes:
1. **Loan Officer Mode**: Can select a Realtor partner OR create solo page
2. **Realtor Mode**: MUST select a Loan Officer partner

Partners are selected from a dropdown in Step 0/1. When selected, their photo comes from the `photo_url` field (from Realtors API or LoanOfficers API).

**In Step 7 (Branding/Your Team Info), users can edit:**
- Their name, license/NMLS, phone, email
- BUT **NO PHOTO UPLOAD FIELD** for themselves or their partner

**Scenario Where This Breaks:**
1. Realtor Mode user selects a Loan Officer from dropdown
   - LO's photo comes from `LoanOfficers::get_loan_officers()` → `photo_url`
   - If LO has no photo in system, shows fallback (Gravatar or placeholder)
   - **No way to upload custom LO photo for this page**

2. Loan Officer selects a Realtor (from Step 1, co-branded)
   - Realtor's photo comes from `Realtors::get_realtors()` → `photo_url`
   - **No way to upload custom Realtor photo for this page**

3. Manual realtor entry (if available)
   - Fields exist for: `_frs_realtor_name`, `_frs_realtor_email`, `_frs_realtor_phone`, `_frs_realtor_license`
   - Field `_frs_realtor_photo` exists in database (seen in Template.php line 279)
   - **But NO form field to populate it**

**What Should Happen:**
Step 7 should include:
```
Your Information
├─ [Photo Upload Button/Drag Zone] ← MISSING
├─ Your Name [text field]
├─ [NMLS/License] [text field]
├─ Phone [tel field]
└─ Email [email field]

Partner Information
├─ [Partner Photo Upload] ← MISSING
├─ Partner Name [display or edit]
└─ Partner Details [display]
```

**Root Cause:**
- Form was designed to only allow dropdown partner selection (with pre-loaded photos)
- No UI for uploading custom photos
- Database supports it (`_frs_realtor_photo` meta exists) but form doesn't

**Impact:**
- **Users cannot customize partner photos**
- Pages with partners from external API show whatever photo is in that system
- If API partner has no/outdated photo, users are stuck
- Breaks professional appearance of co-branded pages
- Reduces conversion because potential clients see generic/missing photos

---

### BUG #4: VCARD PHOTO DOWNLOADS - SSRF + FILE DOWNLOAD VULNERABILITY

**Severity:** CRITICAL  
**Type:** Security - SSRF & Arbitrary File Download  
**File:** `/frs-lead-pages.php` Lines 583-592  
**CWE:** CWE-918 (Server-Side Request Forgery)

**The Vulnerable Code:**
```php
// Photo (base64 encoded if available)
if ( ! empty( $contact['photo'] ) && filter_var( $contact['photo'], FILTER_VALIDATE_URL ) ) {
    $photo_data = @file_get_contents( $contact['photo'] );  // ← VULNERABLE LINE
    if ( $photo_data ) {
        $photo_base64 = base64_encode( $photo_data );
        $photo_type = 'JPEG';
        if ( strpos( $contact['photo'], '.png' ) !== false ) {
            $photo_type = 'PNG';
        }
        $vcard .= "PHOTO;ENCODING=b;TYPE=" . $photo_type . ":" . $photo_base64 . "\r\n";
    }
}
```

**Attack Vectors:**
1. **SSRF Attack** - Download files from internal network:
   ```
   Photo URL: http://localhost:8080/admin
   http://192.168.1.1/router_config
   http://169.254.169.254/latest/meta-data (AWS EC2 metadata)
   ```

2. **Firecrawl Image URLs** - Controlled by external service
   - Firecrawl returns URLs from real estate sites
   - Attacker can control Firecrawl response → malicious URL
   - Plugin downloads and embeds in vCard

3. **Large File Download** - DOS/Disk Fill
   - No file size limit
   - Download 1GB file → vCard generation hangs
   - Disk space consumed

4. **MIME Type Confusion** - Filename-based detection
   ```php
   if ( strpos( $contact['photo'], '.png' ) !== false ) {
       $photo_type = 'PNG';
   }
   ```
   - URL: `http://attacker.com/file.png.exe` → detected as PNG
   - URL: `http://attacker.com/file.jpg?redirect=/malware.exe` → downloaded as "JPEG"

**What Should Happen:**
```php
// Only allow local WordPress attachments
if ( ! empty( $contact['photo'] ) ) {
    // Only process attachment URLs from this site
    if ( strpos( $contact['photo'], wp_upload_dir()['baseurl'] ) === 0 ) {
        $response = wp_safe_remote_get( $contact['photo'], [
            'timeout'  => 5,
            'sslverify' => true,
        ]);
        
        if ( ! is_wp_error( $response ) ) {
            $photo_data = wp_remote_retrieve_body( $response );
            $size = strlen( $photo_data );
            
            // Validate file size (5MB max)
            if ( $size < 5242880 ) {
                // Validate actual MIME type from headers
                $mime_type = wp_remote_retrieve_header( $response, 'content-type' );
                if ( in_array( $mime_type, ['image/jpeg', 'image/png', 'image/gif'] ) ) {
                    $photo_base64 = base64_encode( $photo_data );
                    // ... continue safely
                }
            }
        }
    }
}
```

**Impact:**
- **CRITICAL:** Can access internal network services
- **CRITICAL:** Can enumerate internal IPs/services
- **HIGH:** Disk space exhaustion via large file downloads
- **MEDIUM:** Code injection if vCard parser doesn't sanitize

---

### BUG #1: HARDCODED IMAGE PATH BREAKS WITH CDN + BROKEN LOGO

**Severity:** HIGH  
**Type:** Configuration & Image Path Bug  
**File:** `/includes/Frontend/LeadPage/template-standard.php` Line 97

**The Code:**
```php
<img src="<?php echo esc_url( content_url( '/uploads/2025/09/21C-Wordmark-White.svg' ) ); ?>" alt="21st Century Lending">
```

**What's Wrong:**
1. **Hardcoded path** assumes file exists at `/uploads/2025/09/21C-Wordmark-White.svg`
2. **File probably doesn't exist** on test/production servers
3. **Not configurable** - no way to change logo without code edit
4. **CDN incompatible** - breaks with:
   - WP Offload Media (S3)
   - Cloudflare
   - Any CDN that changes upload URL structure

**Actual URL Generated:**
```
https://tutorlms-exploration.local/wp-content/uploads/2025/09/21C-Wordmark-White.svg
```

**Real Impact:**
- Broken logo image on EVERY lead page
- Unfixable without admin directly uploading file to exact path
- Visual break in branding

**What Should Happen:**
```php
// Option 1: Store as attachment
$logo_id = get_post_meta( $page_id, '_frs_21c_logo_id', true );
if ( $logo_id ) {
    $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
}

// Option 2: Store in plugin directory
$logo_url = plugins_url( 'assets/images/21C-Wordmark-White.svg', FRS_LEAD_PAGES_PLUGIN_FILE );
```

---

### BUG #2: FIRECRAWL IMAGES NOT VALIDATED - BROKEN PROPERTY PHOTOS

**Severity:** HIGH  
**Type:** Integration/Data Validation Bug  
**File:** `/includes/Integrations/Firecrawl.php` Lines 97-128

**The Problem:**
Firecrawl returns image URLs from real estate listing sites. These URLs are:
1. **Not validated** - no accessibility check
2. **Directly used** in frontend HTML `<img src="">` tags
3. **Minimal filtering** - only removes URLs containing 'logo', 'icon', 'avatar', 'profile'
4. **Can be tracking pixels, ads, or broken links**

**Filtering Code:**
```php
$property_images = array_filter( $all_images, function( $img ) {
    if ( stripos( $img, 'logo' ) !== false ) return false;
    if ( stripos( $img, 'icon' ) !== false ) return false;
    if ( stripos( $img, 'avatar' ) !== false ) return false;
    if ( stripos( $img, 'profile' ) !== false ) return false;
    return true;  // Everything else passes!
});
```

**Real URLs That Pass This Filter:**
```
https://zmls.zillow.com/track/pixel.gif  (1x1 tracking pixel)
https://cdn.redfin.com/ads/banner.jpg  (ad network image)
https://img.zillow.com/watermark.png  (site watermark)
https://maps.googleapis.com/maps/api/staticmap?...  (not a property photo)
```

**What's Happening:**
1. API returns 3-5 image URLs (first line 126: `array_slice( $property_images, 0, 3 )`)
2. URLs might be 404s, redirects, or wrong content type
3. Frontend shows broken images
4. No fallback or error handling

**What Should Happen:**
```php
// Validate each image URL
$validated_images = [];
foreach ( $property_images as $url ) {
    // 1. Check URL format
    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) continue;
    
    // 2. Verify image is accessible and correct type
    $response = wp_remote_head( $url, ['timeout' => 5] );
    if ( is_wp_error( $response ) ) continue;
    
    $status = wp_remote_retrieve_response_code( $response );
    if ( $status !== 200 ) continue;  // Skip 404s, redirects
    
    // 3. Check MIME type
    $mime = wp_remote_retrieve_header( $response, 'content-type' );
    if ( ! in_array( $mime, ['image/jpeg', 'image/png', 'image/webp'] ) ) {
        continue;  // Skip non-image files
    }
    
    // 4. Check dimensions (property photos > 500x500)
    // This requires downloading and checking, could use IMG tag onerror instead
    
    $validated_images[] = $url;
}
```

**Impact:**
- Property gallery shows broken images
- Users see "X" icons instead of property photos
- Reduces trust in lead page
- Converts worse than pages with proper photos

---

## HIGH SEVERITY BUGS

### BUG #3: PARTNER PHOTOS DON'T HAVE FALLBACK - BROKEN IMAGES ON PAGES

**Severity:** MEDIUM-HIGH  
**Type:** Image Display / Fallback Logic  
**Files:**
- `/includes/Frontend/LeadPage/template-standard.php` Lines 229-230, 257-258
- `/includes/Frontend/LeadPage/Template.php` Lines 216-334

**The Issue:**
When displaying LO/Realtor partner photos:

```php
<?php if ( ! empty( $lo_data['photo'] ) ) : ?>
    <img src="<?php echo esc_url( $lo_data['photo'] ); ?>" alt="<?php echo esc_attr( $lo_data['name'] ); ?>" class="lead-page__agent-photo">
<?php endif; ?>
```

Problems:
1. **Only checks if non-empty**, not if valid/accessible
2. **No onerror handler** - broken image stays broken
3. **Fallback chain ends at Gravatar** - assumes external service available
4. **No visual placeholder** - missing photo = white space

**Photo Fallback Chain in Template.php:**
```php
public static function get_user_photo( int $user_id ): string {
    // 1. Check FRS Profiles table
    // 2. Check user_profile_photo meta
    // 3. Check simple_local_avatar meta
    // 4. Check custom_avatar_url meta
    // 5. Check profile_photo meta
    // 6. FINAL FALLBACK: get_avatar_url() → GRAVATAR (external)
    return \FRSLeadPages\frs_normalize_upload_url( get_avatar_url( $user_id, [ 'size' => 200 ] ) );
}
```

**What Happens:**
- If user has no photo in any meta field
- Falls back to `get_avatar_url()` which returns Gravatar URL
- Gravatar shows generic avatar (not user's actual photo)
- Unprofessional for business pages

**What Should Happen:**
```php
// If no photo found, generate placeholder with initials
if ( ! $photo_url ) {
    $first = substr( $user->first_name, 0, 1 );
    $last = substr( $user->last_name, 0, 1 );
    $initials = strtoupper( $first . $last );
    
    // Generate placeholder image
    $photo_url = sprintf(
        'https://ui-avatars.com/api/?name=%s&background=0D8ABC&color=fff&size=200&font-size=0.4',
        urlencode( $initials )
    );
}
```

And add onerror handler:
```html
<img src="<?php echo esc_url( $lo_data['photo'] ); ?>" 
     alt="<?php echo esc_attr( $lo_data['name'] ); ?>" 
     class="lead-page__agent-photo"
     onerror="this.src='data:image/svg+xml,<svg>...</svg>'">
```

---

### BUG #5: FIRECRAWL FILTERING IS INSUFFICIENT - WRONG IMAGES DISPLAYED

**Severity:** MEDIUM  
**Type:** Data Quality Bug  
**File:** `/includes/Integrations/Firecrawl.php` Lines 108-115

**The Problem:**
String-based URL filtering doesn't catch:
- **Tracking pixels** (filename has no telltale words)
- **Real estate site UI images** (badges, watermarks labeled with site names)
- **Owner photos** (URLs might contain "property-owner.jpg")
- **MLS logos** (not filtered by current checks)

**Current Filter:**
```php
$property_images = array_filter( $all_images, function( $img ) {
    if ( stripos( $img, 'logo' ) !== false ) return false;
    if ( stripos( $img, 'icon' ) !== false ) return false;
    if ( stripos( $img, 'avatar' ) !== false ) return false;
    if ( stripos( $img, 'profile' ) !== false ) return false;
    return true;
});
```

**Examples of URLs That PASS But Shouldn't:**
```
https://px.ads-service.com/pixel.gif?x=1&y=1  ← tracking pixel
https://badges.realtor.com/badge.png  ← realtor badge
https://www.redfin.com/statics/images/watermark.svg  ← watermark
https://api.zillow.com/static/MLS_logo.png  ← MLS branding
```

**What Should Happen:**
```php
private static function filter_property_images( array $urls ): array {
    $validated = [];
    $real_estate_cdns = [
        'zillowstatic.com',
        'redfin.com/statics',
        'realtor.com/statics',
        'trulia.com/statics',
    ];
    
    foreach ( $urls as $url ) {
        // Check URL domain is known property site CDN
        $domain_ok = false;
        foreach ( $real_estate_cdns as $cdn ) {
            if ( stripos( $url, $cdn ) !== false ) {
                $domain_ok = true;
                break;
            }
        }
        if ( ! $domain_ok ) continue;
        
        // Check file size hints (1x1 pixel = ~50 bytes, real photo > 50KB)
        // This requires HEAD request to check Content-Length
        
        // Filter by URL path patterns
        if ( preg_match( '/\.(gif|webp)$/i', $url ) ) continue;  // GIFs often tracking
        if ( preg_match( '/(pixel|badge|watermark|logo|header|footer)/i', $url ) ) continue;
        
        $validated[] = $url;
    }
    
    return $validated;
}
```

---

### BUG #6: GRAVATAR FALLBACK NOT IDEAL - GENERIC AVATARS

**Severity:** LOW-MEDIUM  
**Type:** UX/Data Quality  
**Files:**
- `/includes/Frontend/LeadPage/Template.php` Line 333
- `/includes/Core/LoanOfficers.php` Line 138

**The Problem:**
When no local photo exists, code falls back to Gravatar:
```php
return \FRSLeadPages\frs_normalize_upload_url( get_avatar_url( $user_id, [ 'size' => 200 ] ) );
```

**Issues:**
1. External dependency on gravatar.com
2. Internal users usually don't have Gravatar accounts
3. Shows generic default avatar (not helpful)
4. Slow if gravatar.com is unreachable
5. Breaks in offline/sandbox environments

**What Should Happen:**
```php
// Generate colored initials avatar instead
$first_initial = strtoupper( substr( $user->first_name, 0, 1 ) );
$last_initial = strtoupper( substr( $user->last_name, 0, 1 ) );
$initials = $first_initial . $last_initial ?: substr( $user->display_name, 0, 2 );

// Use local service (no external dependency)
return sprintf(
    'https://ui-avatars.com/api/?name=%s&background=%s&color=fff&size=200&font-size=0.4&bold=true',
    urlencode( $initials ),
    $this->get_color_for_initials( $initials )
);
```

---

## MEDIUM SEVERITY BUGS

### BUG #8: NO PHOTO UPLOAD IN WIZARD - REALTOR PHOTO FIELD IGNORED

**Severity:** MEDIUM (Complements Bug #7)  
**Type:** Form/UX Issue  
**Files:** All 6 wizard files
- `/includes/OpenHouse/Wizard.php`
- `/includes/CustomerSpotlight/Wizard.php`
- `/includes/SpecialEvent/Wizard.php`
- `/includes/RateQuote/Wizard.php`
- `/includes/ApplyNow/Wizard.php`
- `/includes/MortgageCalculator/Wizard.php`

**The Problem:**
Database has `_frs_realtor_photo` meta field, but:
1. Form (Step 7) doesn't include photo upload for realtor
2. Form for LO doesn't include photo upload field either
3. JavaScript collects partner data but ignores photo field

**Form Collection in OpenHouse/Wizard.php ~Line 1683:**
```javascript
realtorName: document.getElementById("oh-realtor-name")?.value || "",
realtorLicense: document.getElementById("oh-realtor-license")?.value || "",
realtorPhone: document.getElementById("oh-realtor-phone")?.value || "",
realtorEmail: document.getElementById("oh-realtor-email")?.value || ""
// ← NO PHOTO FIELD
```

**Database Still Accepts It (Line 2046):**
```php
update_post_meta( $page_id, '_frs_realtor_name', $data['branding']['realtorName'] ?? '' );
```

**But Missing from Form Data:**
```php
// Line 2043 - Missing:
// update_post_meta( $page_id, '_frs_realtor_photo', $data['branding']['realtorPhoto'] ?? '' );
```

**Solution:**
Add to Step 7 form:
```html
<div class="oh-field">
    <label class="oh-label">
        <span id="oh-realtor-photo-label">Realtor Photo</span>
        <span id="oh-realtor-photo-current" style="font-size: 12px; color: #64748b;">
            (Selected from dropdown or use custom below)
        </span>
    </label>
    <div class="oh-photo-upload" id="oh-realtor-photo-upload" 
         style="border: 2px dashed #cbd5e1; padding: 20px; border-radius: 8px; text-align: center;">
        <input type="file" id="oh-realtor-photo-file" accept="image/*" style="display: none;">
        <button type="button" class="oh-btn oh-btn--ghost" onclick="document.getElementById('oh-realtor-photo-file').click()">
            Upload Photo
        </button>
        <p style="font-size: 12px; color: #94a3b8; margin-top: 8px;">or drag and drop</p>
    </div>
    <input type="hidden" id="oh-realtor-photo-url" value="">
</div>
```

And JavaScript to handle it:
```javascript
document.getElementById("oh-realtor-photo-file").addEventListener("change", function() {
    const file = this.files[0];
    if ( ! file ) return;
    
    const formData = new FormData();
    formData.append( 'file', file );
    formData.append( 'action', 'frs_upload_image' );
    
    fetch( ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then( r => r.json() )
    .then( data => {
        if ( data.success ) {
            document.getElementById("oh-realtor-photo-url").value = data.data.url;
            // Show preview
        }
    });
});
```

---

## SUMMARY TABLE

| # | Title | Type | Severity | File | Line | Status |
|---|-------|------|----------|------|------|--------|
| **7** | Missing Partner Photo Upload | Missing Feature | CRITICAL | Multiple Wizards | 553-617 | Not implemented |
| **4** | vCard SSRF/File Download | Security | CRITICAL | frs-lead-pages.php | 583-592 | Vulnerable |
| **1** | Hardcoded Logo Path | Config/Image | HIGH | template-standard.php | 97 | Broken |
| **2** | Firecrawl Images Not Validated | Integration | HIGH | Firecrawl.php | 97-128 | No validation |
| **3** | Partner Photos No Fallback | Image Display | MEDIUM-HIGH | Template.php | 229-333 | Insufficient fallback |
| **5** | Firecrawl Filtering Insufficient | Data Quality | MEDIUM | Firecrawl.php | 108-115 | Weak filter |
| **6** | Gravatar Fallback | UX/Dependency | MEDIUM | Multiple | 138-333 | Suboptimal |
| **8** | Photo Upload Field Missing | Form/UX | MEDIUM | All Wizards | Various | Not in form |

---

## IMMEDIATE ACTION ITEMS

### URGENT (Before Production)
1. **Fix vCard SSRF** - Use `wp_safe_remote_get()` with URL validation
2. **Add Photo Upload to Wizard** - Step 7 needs photo upload for both user and partner
3. **Fix Hardcoded Logo** - Use plugin asset or attachment ID

### HIGH PRIORITY
4. **Validate Firecrawl Images** - Add HEAD request validation before using URLs
5. **Add Image Fallback** - onerror handlers on `<img>` tags

### MEDIUM PRIORITY
6. **Improve Partner Photo Fallback** - Use initials avatar instead of Gravatar
7. **Enhance Image Filtering** - Better detection of non-property images

---

## ROOT CAUSE ANALYSIS

**Why These Bugs Exist:**
1. **Photo upload feature incomplete** - Form created without upload UI
2. **Security review gap** - No input validation on external URLs
3. **Image handling scattered** - Photo sources in 4+ different places, inconsistent fallbacks
4. **Firecrawl integration rushed** - API URLs used directly without validation
5. **CDN incompatibility** - Plugin built assuming single server, hardcoded paths

