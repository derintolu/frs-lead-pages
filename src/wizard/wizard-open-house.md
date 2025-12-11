# Open House Wizard

## Overview
Single-screen, no-scroll sign-in page for open house property tours. Co-branded between realtor and loan officer.

---

## Step 0: Choose Your Loan Officer

**Header:** Partner Up  
**Subheader:** Select a loan officer to co-brand this page

**Input:** Dropdown or search  
- Placeholder: "Search by name..."
- List displays: LO Name, NMLS #, Photo

**Helper text:** "Your loan officer's info and branding will appear on the page. Don't have a preferred LO? [View our team](#)"

**Button:** Continue

---

## Step 1: Property Lookup

**Header:** Let's Find the Property  
**Subheader:** Enter the address and we'll pull the details for you

**Input:** Address field  
- Placeholder: "123 Main St, City, CA 94000"

**Button:** Find Property

**Loading state:** "Searching for property details..."

**Error state:**  
- Message: "We couldn't find that property. Please check the address or enter details manually."
- **Button:** Enter Manually

---

## Step 2: Confirm Property Details

**Header:** Does This Look Right?  
**Subheader:** We pulled these details automatically — feel free to edit anything

| Field | Placeholder/Default |
|-------|---------------------|
| Price | $0,000,000 |
| Address | 123 Main St, City, CA 94000 |
| Bedrooms | 0 |
| Bathrooms | 0 |
| Square Feet | 0,000 |

**Helper text:** "This info will display on your sign-in page."

**Button:** Looks Good

---

## Step 3: Choose Your Photo

**Header:** Pick Your Hero Image  
**Subheader:** Select one photo to feature on your page

**Display:** Grid of 4-8 images from listing (selectable, single choice)

**Fallback (no images found):**  
- Message: "We couldn't find listing photos. Upload your own below."
- **Upload button:** Upload Image

**Helper text:** "Choose the photo that shows the property at its best."

**Button:** Continue

---

## Step 4: Customize Your Page

**Header:** Make It Yours  
**Subheader:** Choose your headline and welcome message

### Headline
- Input type: Dropdown + custom option
- Label: "Headline"
- Options:
  - Welcome!
  - You're Invited
  - Come On In
  - Thanks for Visiting
  - We've Been Expecting You
  - Custom...
- Placeholder (if custom): "Enter your headline"

### Subheadline
- Input type: Dropdown + custom option
- Label: "Subheadline"
- Options:
  - Please sign in to tour this property
  - Sign in to get more info on this home
  - We'd love to know who's visiting
  - Quick sign-in to continue your tour
  - Custom...
- Placeholder (if custom): "Enter your subheadline"

### Button Text
- Label: "Button Text"
- Default: "Sign In"

### Consent Text
- Label: "Fine Print"
- Default: "By signing in, you agree to receive communications about this property and financing options."

**Button:** Continue

---

## Step 5: Form & Lead Questions

**Header:** What Do You Want to Know?  
**Subheader:** Toggle which fields visitors will fill out

### Contact Fields

| Field | Default | Required |
|-------|---------|----------|
| Full Name | On | Yes |
| Email | On | Yes |
| Phone Number | On | Yes |
| Comments | On | No |

### Qualifying Questions

| Question | Default | Options |
|----------|---------|---------|
| Are you working with an agent? | On | Yes / No |
| Are you pre-approved for financing? | On | Yes / No |
| Are you interested in getting pre-approved? | On | Yes / No / Already approved |
| When are you looking to buy? | Off | ASAP / 1-3 months / 3-6 months / Just browsing |
| Is this your first home purchase? | Off | Yes / No |

**Helper text:** "Leads who aren't pre-approved will be flagged for your loan officer to follow up."

**Button:** Continue

---

## Step 6: Branding & Team

**Header:** Add Your Team  
**Subheader:** Your info will appear on the page so visitors know who to contact

### Realtor Info

| Field | Input |
|-------|-------|
| Photo | Upload or pull from profile |
| Name | Text (pre-filled from account) |
| Phone | Text (pre-filled) |
| Email | Text (pre-filled) |
| License # | Text (pre-filled) |

### Loan Officer Info
*Pre-filled from Step 0*

| Field | Input |
|-------|-------|
| Photo | From LO profile |
| Name | Text |
| NMLS # | Text |
| Phone | Text |
| Email | Text |

### Logos

| Logo | Input |
|------|-------|
| Brokerage Logo | Upload or select (Century 21 Masters default) |
| Lender Logo | Upload or select (21st Century Lending default) |

**Helper text:** "Both logos will appear in the footer. Team photos appear near the form."

**Button:** Continue

---

## Step 7: Preview & Publish

**Header:** You're All Set  
**Subheader:** Preview your page, then publish when ready

**Preview:** Live rendered preview of the page

### Actions

| Button | Action |
|--------|--------|
| Edit | Go back to any step |
| Copy Link | Copies page URL |
| Download QR Code | Downloads QR for print |
| Publish | Makes page live |

**Helper text:** "After publishing, you can view and manage leads from your Generation Station dashboard."

### Post-Publish Confirmation
- Message: "Your page is live!"
- Buttons: [View Page] [Copy Link] [Download QR] [Create Another]

---

## Page Layout Reference

**Left Side (60%)**
- Hero property image (full bleed)
- Price overlay (bottom left)
- Address overlay (below price)
- Stats bar: Beds | Baths | Sq Ft

**Right Side (40%)**
- Headline
- Subheadline
- Form fields
- Submit button
- Consent text
- Team photos (realtor + LO)
- Logos in footer

**No scroll — single screen**
