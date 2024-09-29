---
title: FAQ
description: Answers to popular questions about Laravel
---

# FAQ

[[toc]]

## How to restart the last migration?

```sh
artisan migrate:refresh --step 1
```

## How to generate IDE helper comments for a model?

<!-- prettier-ignore-start -->
> [!IMPORTANT]
> This requires the `laravel-ide-helper` package to be installed.
> ```sh
> composer req barryvdh/laravel-ide-helper --dev
> ```
<!-- prettier-ignore-end -->

```sh
artisan ide-helper:models "App\Models\Post" "App\Models\User"
```
