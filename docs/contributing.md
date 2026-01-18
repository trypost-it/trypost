# Contributing to TryPost

Thank you for your interest in contributing to TryPost! This guide will help you get started.

## Ways to Contribute

- **Report bugs** - Found a bug? Open an issue
- **Suggest features** - Have an idea? Start a discussion
- **Improve docs** - Fix typos, add examples, clarify instructions
- **Write code** - Fix bugs or implement new features

## Development Setup

### Prerequisites

- PHP 8.2+
- Node.js 18+
- PostgreSQL or MySQL
- Redis
- Composer

### Setup

1. Fork and clone the repository:

```bash
git clone https://github.com/YOUR_USERNAME/trypost.git
cd trypost
```

2. Install dependencies:

```bash
composer install
npm install
```

3. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

4. Set up database and run migrations:

```bash
php artisan migrate
```

5. Start development servers:

```bash
# Terminal 1 - Laravel
php artisan serve

# Terminal 2 - Vite
npm run dev

# Terminal 3 - Queue worker
php artisan horizon:watch
```

## Code Style

### PHP

We use [Laravel Pint](https://laravel.com/docs/pint) for PHP code formatting:

```bash
# Check code style
vendor/bin/pint --test

# Fix code style
vendor/bin/pint
```

### JavaScript/TypeScript

We use ESLint and Prettier:

```bash
# Lint
npm run lint

# Format
npm run format
```

## Testing

Run the test suite before submitting changes:

```bash
php artisan test
```

## Pull Request Process

1. **Create a branch** from `develop`:

```bash
git checkout -b feature/your-feature-name
```

2. **Make your changes** and commit with clear messages

3. **Run tests** and ensure they pass

4. **Run code formatters**:

```bash
vendor/bin/pint
npm run lint
npm run format
```

5. **Push** and create a pull request to the `develop` branch

6. **Describe your changes** in the PR description

## Commit Messages

Write clear commit messages:

- `fix: resolve login redirect issue`
- `feat: add Pinterest scheduling support`
- `docs: update installation guide`
- `refactor: simplify post creation logic`

## Questions?

- [GitHub Discussions](https://github.com/trypost-it/trypost/discussions) - For questions and ideas
- [GitHub Issues](https://github.com/trypost-it/trypost/issues) - For bugs and feature requests

## License

By contributing, you agree that your contributions will be licensed under the same license as the project (FSL).
