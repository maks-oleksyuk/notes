---
title: 'JSON Schema Sorter: Order JSON Keys by $schema'
menu_title: 'JSON Schema Sorter'
description:
  "Free online tool to sort JSON object keys to match their $schema property.
  Paste JSON, get it reordered to follow the schema's property order, and copy
  the result. Runs entirely in your browser, nothing is uploaded."
head:
  - - meta
    - name: keywords
      content:
        'json sorter, json schema, sort json keys, json formatter, json schema
        order, reorder json properties, composer.json sort, online json tool'
aside: false
---

# JSON Schema Sorter

Paste JSON that has a `$schema` property. Keys are reordered to match the
schema's `properties` order (recursively), with any remaining keys sorted
alphabetically after. If there's no `$schema`, keys are just sorted
alphabetically.

<JsonSchemaSorter />
