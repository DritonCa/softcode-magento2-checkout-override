# Contributing

1. Branch from `main` using a short prefix: `feat/…`, `fix/…`, `test/…`, `docs/…`.
2. Keep commits small and focused; write imperative commit messages.
3. Run the same checks as CI before opening a pull request:
   ```bash
   find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
   phpcs --standard=Magento2 --extensions=php,phtml -n --ignore='*/vendor/*,*/Test/bootstrap.php' .
   phpunit -c phpunit.xml.dist
   ```
   The unit tests run without a Magento installation (see `Test/bootstrap.php`).
4. Keep project-specific business rules out of the module. Add an extension point
   (a DI argument, an interface or an event) instead, and document it in the README.
5. Update the README and CHANGELOG when behaviour changes.
