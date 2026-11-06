---
title: Dhivehi and RTL
sidebar_position: 11
---

Every string the package shows is translated into Dhivehi (`lang/dv.json`), including validation messages, step types, condition operators, action names and the tester's explanations. With the app's locale `dv`:

- pages, messages, the history and notifications read in Dhivehi;
- the builder's inputs for Dhivehi text type Thaana and run right to left;
- the diagram mirrors: positions are logical (from the inline start), so a workflow drawn left to right reads right to left;
- the styles use logical properties only (`margin-inline-start`, `inset-inline-end`, …), and the views logical utilities (`ms-`, `ps-`, `text-start`).

Write step names and labels in Dhivehi in your definitions, or as locale maps with [translatable](translatable.md). Publish the translations to change them:

```bash
php artisan vendor:publish --tag=workflows-translations
```

# For review

These translations need a Dhivehi speaker to check them:

| English | Dhivehi | Note |
| --- | --- | --- |
| Workflow | ވޯކްފްލޯ | transliterated; a Dhivehi term may read better |
| Transition | ކުރިއަށްދާ މަގު | "the way forward"; used for the arrows between steps |
| Instance | ހިންގުން | "a run" of a workflow |
| Decision | ނިންމުން | |
| Assigned to | ހަވާލުކުރެވިފައިވަނީ | |
| Publish | ޝާއިޢުކުރޭ | |
| Draft | ޑްރާފްޓް | transliterated |
| Outcome | ނަތީޖާ | |
| Subject (the record a workflow is about) | ބެހޭ ރެކޯޑް | |
| Orange | ރަތްމުށި | |
| is more than / is at most (operators) | ވުރެ ގިނަ / ގިނަވެގެން | read after the field name |
| Signing secret | ސޮއިކުރާ ސިއްރު | |
