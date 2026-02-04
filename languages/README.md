# Translation template (.pot) generation

Generate the translation template using WP-CLI from the plugin root:

```
wp i18n make-pot . languages/smart-auto-interlinker.pot --domain=smart-auto-interlinker --exclude="vendor,node_modules"
```

This keeps the `languages/` directory aligned with all translatable strings in the plugin.
