# Taxmod DPD

DPD connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin).

**Status 0.0.1 (needs modeller 0.1.89 and [Taxmod Mail](https://github.com/sfambach/wp_tax_mail) 0.6.0 or later):
reads DPD parcel notices from the mailbox of Taxmod Mail and hands them to the modeller's inbox.** A notice carries no
shop order number; the modeller gives it to the order with the same parcel number, from whatever shop (D-1017, D-1020),
and never creates an order from it.

| Subject | Status |
|---|---|
| «Bald ist Ihr DPD Paket da» | on the way |
| «Zwischen HH:MM und HH:MM Uhr kommt Ihr DPD Paket» | on the way |
| «Ihr Paket ist da!» | delivered |
| «Die Abstellung Ihres DPD Pakets ist erfolgt» | delivered |

Both mail layouts are read: the one until January 2021 («Ihre Sendung … von …») and the one since February 2021
(«Versender & Paketnummer»). The 14-digit parcel number comes from the text, or else from the tracking link.

DPD's tracking API (DPD Cloud Service, «getParcelLifeCycle») needs a registration as a business customer, so the mails
are the source for now. Every connection is a module of its own (D-1006,
[concept](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md)).

## Setup

1. Install and set up Taxmod Mail (mailbox access).
2. Activate this module. *Taxonomy Modeller → Configuration → DPD* shows under «Mail» which mails it reads.

## Development

`composer install`, then `vendor/bin/phpunit` (core suite, no WordPress). The sample mails in `tests/Core/fixtures`
are real DPD notices with names, addresses and numbers replaced.

## License

Copyright (C) 2026 Stefan Fambach (SteFam), https://www.fambach.net — [PolyForm Noncommercial 1.0.0](LICENSE.md).
Commercial use needs the owner's prior consent.

## Authors

- **Stefan Fambach** (SteFam) – idea and concept, review and testing – https://www.fambach.net
- **Claude** (Anthropic AI) – implementation, via Claude Code

## AI disclosure

This project is built with AI assistance. Stefan Fambach had the idea, wrote the concept and reviews and tests every change; the implementation (code and documentation) is written largely by Claude (Anthropic) via Claude Code.
