# Contributing to Document Verification Portal

Thank you for your interest in contributing to this project! Here's how you can help.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Set up your development environment (see README.md)
4. Create a new branch for your feature or fix

## Development Setup

```bash
# Clone your fork
git clone https://github.com/yourusername/verification-portal.git

# Navigate to project
cd verification-portal

# Copy config template and configure
cp verification/config/db.example.php verification/config/db.php
# Edit db.php with your local database credentials
```

## Code Style Guidelines

### PHP
- Use PSR-12 coding standards
- Indent with 4 spaces
- Use meaningful variable and function names
- Add comments for complex logic
- Always sanitize user inputs
- Use prepared statements for database queries

### JavaScript
- Use ES6+ syntax where supported
- Keep functions small and focused
- Use meaningful variable names

### CSS/Tailwind
- Follow the existing color scheme
- Use Tailwind utility classes
- Keep custom CSS minimal

## Submitting Changes

### Pull Request Process

1. **Update your branch** with the latest main branch
   ```bash
   git checkout main
   git pull origin main
   git checkout your-branch
   git rebase main
   ```

2. **Test your changes** thoroughly
   - Test all affected functionality
   - Check for PHP errors
   - Verify responsive design

3. **Create a pull request** with:
   - Clear title describing the change
   - Description of what was changed and why
   - Screenshots for UI changes
   - Reference any related issues

### Commit Messages

Use clear, descriptive commit messages:

```
feat: add document export feature
fix: resolve OTP validation issue
docs: update installation instructions
style: improve mobile responsiveness
refactor: simplify date conversion logic
```

## Reporting Issues

When reporting bugs, please include:
- Description of the issue
- Steps to reproduce
- Expected behavior
- Actual behavior
- PHP version and server environment
- Screenshots if applicable

## Feature Requests

For new features:
- Describe the feature and its use case
- Explain why it would benefit users
- Consider how it fits with existing functionality

## Security

If you discover a security vulnerability:
- Do NOT open a public issue
- Email the maintainers directly
- Provide details about the vulnerability
- Allow time for a fix before disclosure

## Questions?

Feel free to open an issue for questions about contributing.

---

Thank you for helping improve the Document Verification Portal!
