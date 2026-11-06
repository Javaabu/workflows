---
title: AI assistants
sidebar_position: 1.25
---

The package ships guidance for AI coding assistants. This is about assistants that help *you* write code with the package. The assistant *in* the builder, which designs workflows for admins, is described in [AI assistant](advanced-usage/ai-assistant.md).

- **Laravel Boost.** `php artisan boost:install` (or `boost:update`) picks up the package's guideline (`resources/boost/guidelines/core.blade.php`) and the `javaabu-workflows` skill (`resources/boost/skills/javaabu-workflows`). The skill covers:
  - defining and seeding workflows
  - starting instances and taking transitions
  - assignees, conditions, actions and step forms
  - the routes, pages and permissions
  - custom actions and assignee types
  - testing, and common mistakes
- **llms.txt.** `vendor/javaabu/workflows/llms.txt` has the rules for using the package and an index of every docs page. Point an assistant at it when Boost isn't installed.
