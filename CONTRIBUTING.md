# Contributing

Phasetime's a personal project, built to stop rebuilding login for every small thing I ship. Still happy to take contributions if you find bugs or want to add something.

## Before you open a PR

- Keep it plain PHP. No framework, no Composer dependencies unless there's a genuinely good reason. Part of the point is that anyone can read the whole thing top to bottom in one sitting.
- Match the existing style: PDO with prepared statements, plain functions rather than classes outside the SDK, Tailwind via the CDN script, same color and font tokens already in `assets/`.
- There's no automated test suite. Test manually against a real PHP 8.1+ and MySQL setup before submitting, and say what you tested in the PR description.
- Touching `includes/auth.php`, `authorize.php`, `token.php`, or the captcha? Explain the security reasoning behind the change, not just what changed. That code gets read carefully.

## Bugs and features

Open an issue. For bugs, include your PHP and MySQL versions plus what you expected versus what happened. For features, a quick explanation of the use case is more useful than a full spec.

## Security issues

Don't open a public issue for those. See [SECURITY.md](SECURITY.md).

## License

Contributions are MIT licensed, same as the rest of the repo.
