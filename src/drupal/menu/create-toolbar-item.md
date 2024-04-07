---
title: Create toolbar item
---

# Create toolbar item

When developing a project, you have to create some forms and pages with settings
and administrative views, custom entity settings, and so on. It's a good
practice to put all custom settings and pages in a separate menu item so that
all custom settings and UI functionality are collected in a single pile.

## Create the main toolbar route

Usually, a link in the toolbar acts only as a page with other links. To create
such a page, you need to declare a route in the `MODULE_NAME.routing.yml` file
for this page with the following settings:

```yml
module_name.admin:
  path: '/admin/module-name'
  defaults:
    _title: 'Module name'
    _controller: '\Drupal\system\Controller\SystemController::overview'
    link_id: module_name.admin
  requirements:
    _permission: 'access administration pages'
```

`link_id` - the link identifier of the administrative path for which you want to
display child links

This will render child links two levels below the specified link ID, grouped by
the child links one level below.

This is the setting for the normal route, which uses the system controller to
generate the overview page for the submenus and also restricts the route to be
accessible only to administrators. The same controller is used for the
`/admin/config` page.

## Adding links

Now you need to add a link to the created route in the
`MODULE_NAME.links.menu.yml` file:

```yml
module_name.admin:
  title: 'Module Name'
  description: 'Administer and configure my module.'
  route_name: module_name.admin
  parent: system.admin
  weight: 15
```

This will create a link in the administrative menu, indicated by
`parent: system.admin`. If this page does not have any child menus, it will be
displayed as **_You do not have any administrative items_**.

## Add an icon

To add an icon to the toolbar element, you need to add CSS. To do this, create
the `module_name.toolbar.css` file with the following content:

```css
/* For Claro admin theme */
.toolbar-icon.toolbar-icon-modeule-name-admin:before {
  background-image: url(../icons/787878/icon-name.svg);
}

/* For Gin admin theme */
.toolbar .toolbar-bar .toolbar-icon.toolbar-icon-modeule-name-admin:before,
.toolbar-link--has-icon.toolbar-link--modeule-name-admin:before {
  mask-image: url(../icons/787878/icon-name.svg);
}
```

Here is the CSS for 2 popular admin themes, **Claro** and **Gin**, as the
approach to styling their toolbar is different.

After creating the CSS, you need to connect it as a library, for this purpose,
in the `module_name.libraries.yml` file, create a library to which we connect
our style file:

```yml
admin.toolbar:
  version: VERSION
  css:
    theme:
      assets/css/module_name.toolbar.css: {}
```

After declaring the library, you need to connect it to the toolbar using
`hook_preprocess_HOOK`. This hook will only work when the toolbar is displayed.

```php
/**
 * Implements hook_preprocess_HOOK().
 */
function module_name_preprocess_toolbar(&$variables) {
  $variables['#attached']['library'][] = 'module_name/admin.toolbar';
}
```

## Resources

- [Drupal 8: Добавление пункта меню в Toolbar](//niklan.net/blog/138)
- [Menu API](./index.md)
