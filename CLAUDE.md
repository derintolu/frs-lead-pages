# CLAUDE.md - FRS Lead Pages

## Quick Reference

**Plugin:** FRS Lead Pages | **Namespace:** `FRSLeadPages` | **PHP:** 8.1+

## Test URLs (ALWAYS CHECK AFTER CHANGES)

| URL | Purpose |
|-----|---------|
| `https://tutorlms-exploration.local/marketing/lead-pages/` | Main Lead Pages dashboard - wizards render here |
| `https://tutorlms-exploration.local/generation-station/` | Lead Pages wizard page |
| `https://tutorlms-exploration.local/me/` | Portal home - verify no PHP errors |

## Wizards

All wizards render modal containers via `wp_footer` on every frontend page. If ANY wizard has a PHP error, the entire site breaks.

| Wizard | Shortcode | Trigger Class | Color Theme |
|--------|-----------|---------------|-------------|
| Open House | `[open_house_wizard_button]` | `oh-wizard-trigger` | Teal |
| Customer Spotlight | `[customer_spotlight_wizard_button]` | `cs-wizard-trigger` | Blue |
| Special Event | `[special_event_wizard_button]` | `se-wizard-trigger` | Amber |
| Mortgage Calculator | `[mortgage_calculator_wizard_button]` | `mc-wizard-trigger` | Blue |
| Rate Quote | `[rate_quote_wizard_button]` | `rq-wizard-trigger` | Emerald |
| Apply Now | `[apply_now_wizard_button]` | `an-wizard-trigger` | Indigo |

## Key Files

```
frs-lead-pages/
├── frs-lead-pages.php          # Main plugin file, wizard init
├── includes/
│   ├── Core/
│   │   ├── UserMode.php        # LO vs Realtor mode detection
│   │   ├── Realtors.php        # Fetch realtor partners for LOs
│   │   └── LoanOfficers.php    # Fetch LO partners for Realtors
│   ├── OpenHouse/Wizard.php
│   ├── CustomerSpotlight/Wizard.php
│   ├── SpecialEvent/Wizard.php
│   ├── MortgageCalculator/Wizard.php
│   ├── RateQuote/Wizard.php
│   └── ApplyNow/Wizard.php
```

## Bi-Directional Mode

Wizards support both user types:
- **LO Mode**: User selects Solo Page or Co-branded (with realtor partner)
- **Realtor Mode**: User selects LO partner (required)

Use `UserMode::get_mode()` and `UserMode::is_loan_officer()` to detect.

## Common Errors

| Error | Cause | Fix |
|-------|-------|-----|
| Critical error on all pages | PHP error in any wizard | Check `php -l` on all wizard files |
| Wizard modal doesn't open | Missing trigger class | Verify button has correct class |
| Partners not loading | Missing UserMode/Realtors class | Check `use` statements |

## Before Committing

1. Run `php -l` on all modified wizard files
2. Load `/marketing/lead-pages/` in browser
3. Verify no "critical error" message appears
