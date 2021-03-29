# How to contribute

Hi there!
We are welcoming all kinds of contributions:
- Reporting a bug
- Submitting a fix
- Proposing new features
- Discussing future plans


## For all development, we use Github

We use [GitHub](https://github.com/LOVDnl/LOVD3) to host our code, track issues and feature requests, and discuss and accept pull requests.


### Report bugs using Github's issues

We use GitHub issues to track bugs and feature requests.
Report a bug or request a new feature by [opening a new issue](https://github.com/LOVDnl/LOVD3/issues/new).


### Write bug reports with detail, and if relevant, screenshots

Great bug reports tend to have:

- A quick summary and/or background
- Mention which LOVD version you're using, e.g., 3.0 build 26.
- What you expected would happen
- What actually happens
- Steps to reproduce, be specific!
  - Make sure that everybody can easily reproduce the error that you saw.
  - When having issues with imports, include an example import file that shows the problem and which you can freely share with the rest of the world.
  - When seeing errors on the screen, include a screenshot.
- Any additional information (like why you think this might be happening or things you tried that didn't work)

We **love** good bug reports!


### We use Github Flow, so all code changes happen through pull requests

Pull requests are the best way to propose changes to the codebase.
We use [Github Flow](https://guides.github.com/introduction/flow/); to submit a pull request:

1. First, please open a [new issue](https://github.com/LOVDnl/LOVD3/issues/new) to announce your work in advance and so others can be invited to contribute.
2. Fork the repo and create your branch from `master`.
3. If you've added code that should be tested, add tests.
4. If you've changed APIs or existing features, update the documentation to reflect your changes.
5. Ensure the test suite passes.
6. Submit that pull request!


### Use a consistent coding style

The LOVD coding standards are based on the [PHP Pear coding standard](https://pear.php.net/manual/en/standards.php).


### Adding tests

We use continuous integration testing, which includes both unit tests and interface tests using Webdriver and Selenium.
Please ensure your new code passes all test and add tests when necessary.
See the [tests folder](https://github.com/LOVDnl/LOVD3/tree/master/tests).


---
This document was adapted from [Brian A. Danielak's gist](https://gist.github.com/briandk/3d2e8b3ec8daf5a27a62)
 with further inspiration taken [Variant Validator](https://github.com/openvar/variantValidator/blob/master/CONTRIBUTING.md).
