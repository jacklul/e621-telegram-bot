# e621 bot for Telegram

Simple Telegram bot that serves images from [e621.net](https://e621.net) image board, provides basic functionality known from discontinued **[e621 Search](https://telegram.me/e621searchbot)** bot (which is now replaced by this one).

#### Features
- /random command
- Inline search (`@username tags` in any chat), supports e621 links
- MD5 lookup
- Reverse search ([e621.net/iqdb_queries](https://e621.net/iqdb_queries))

Do whatever you want with this, as long as you keep me credited.

This project is ready for deployment in [Google's App Engine](https://cloud.google.com/appengine/) - copy `app.yaml.example` to `app.yaml` and fill out the required variables in `env_variables` section.

## License

See [LICENSE](LICENSE).
