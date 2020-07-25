# e621 bot for Telegram

Simple Telegram bot that serves images from [e621.net](https://e621.net) image board, provides basic functionality known from discontinued **[e621 Search](https://telegram.me/e621searchbot)** bot (which is now replaced by this one).

#### Features
- Inline search (`@bot_username tags` in any chat), supports e621 links
- /random command
- MD5 lookup
- Reverse search ([e621.net/iqdb_queries](https://e621.net/iqdb_queries))
- Group settings

### Group settings

To set settings you have to paste specific string into your group's description and modify configuration variables:

`@bot_username[{"tags":"felid hugging score:>=50","force":0,"antispam":15,"sfw":0}]`

- `tags` - sets the default tags to use when none provided
- `force` - forces default tags and ignores user input (`1` - enabled, `0` - disabled)
- `antispam` - how many seconds to wait between `/random` images
- `sfw` - SFW mode, shows only `rating:safe` images (`1` - enabled, `0` - disabled)

The string between `[` and `]` must be JSON formatted, you can use [this tool](https://jsoneditoronline.org) to make sure it will be valid.

Settings are cached internally for **1 hour**, executing `/settings` command as group administrator reloads the cache. Due to caching on Telegram's side it can take some time for the changes to be available to the bot.

## License

See [LICENSE](LICENSE).
