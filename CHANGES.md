# Changes

## 1.3.7

- Maintenance page is now responsive
- UI improvements

## 1.3.6

- Support reverse proxies in identifying IP addresses
- Added option to have hidden workshops
- Improved SEO
- Added workshop sign in kiosk
- Moved to tales-from-a-dev/tailwind-merge-phpMoved to tales-from-a-dev/tailwind-merge-php
- Dependencies updated
- Admin users are no longer tracked in analytics

## 1.3.5

- Fixed scheduler updating 'Opens soon' workshops to 'Open' based on publish at date

## 1.3.4

- Pick lists are now saved automatically
- Added Artisan test/qa commands
- Fix styling on small checkboxes
- Added link to repo in footer
- Support for remembering email and devices
- Improved error handling of Altcha
- Improved layout of pick list on mobile
- Added inline support for ui.input component
- Fixed issue where pubished at for workshops wasnt being honored
- Workshop status banner now goes beyond card
- Changed 'Scheduled' to 'Opens Soon' status type

## 1.3.3

- Improved password manager support
- Improved styling checkboxes of invoice and quote editor

## 1.3.2

- Changing a quote ID will now redirect to the new ID
- Quote title is now shown in the quote list
- Fixed line item and quantity spacing/alignment in quotes and invoices
- Total columns now specify GST state

## 1.3.1

- Hide popup suggestions when selecting item
- Disable and show loader when saving pick lists
- Pluralize pick list items automatically
- Improved layout of BAS tables
- Improved wording in no tickets found email
- Fixed bug in creating quotes errored
- Fixed bug where address line 2 was not saved

## 1.3.0

- Added workshop pick list support
- Updated dependencies

## 1.2.4

- Improved the display of negative currency amounts

## 1.2.3

- Improved BAS reporting

## 1.2.2

- BAS report did not take refunds into account

## 1.2.1

- Pass altcha checks if config is invalid
- Prevent workshop changes if active tickets are present
- Improved mobile view of admin invoices, payment and audit log pages

## 1.2.0

- Added financial support to the website
- Added ticketing options to workshops
- Added email subscripton management from admin panel
- Do not show 'v' prefix in footer when a direct branch deployed

## 1.1.1

- Updated server information and auto updating logs

## 1.1.0

- Added server information admin page

## 1.0.11

- Fixed malformed delete email template
- Verified return type from browser for deletion messaging
- Fixed login message on account creation to be more accurate

## 1.0.10

- Fixed missing user-delete email template
- Fixed styling on error notifications

## 1.0.9

- Added in dev barryvdh/laravel-ide-helper
- Fixed error in UserDelete class

## 1.0.8

- Fix overzealous bot protection

## 1.0.7

- Fix subscription message exception
- Confirm account deletion by email

## 1.0.6

- Remove Discord links
- Fix subscription renderedAt initalization
- Fixed bad bot protection on register page
- Account: First, Surname and Phone are required only when the other fields are not empty

## 1.0.5

- Fix variable access exception

## 1.0.4

- Updated about page
- Updated dependencies
- Improved bot handling on subscription form
- Fix bot protection on register page

## 1.0.3

- Updated dependencies
- Added project notes

## 1.0.2

- Display version in footer.
