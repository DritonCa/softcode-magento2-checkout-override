# Contributing

1. Branch from `main` using a short prefix: `feat/…`, `fix/…`, `test/…`, `docs/…`.
2. Keep commits small and focused; write imperative commit messages.
3. Run the checks locally before opening a PR:
   ```bash
   find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
   phpcs --standard=Magento2 --extensions=php,phtml -n .
   vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist Test/Unit
   ```
4. Update the README/CHANGELOG when behaviour changes.
