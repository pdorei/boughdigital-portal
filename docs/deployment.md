# Deployment

Target stack:

- Nginx
- PHP-FPM running Laravel
- PostgreSQL
- Redis
- Horizon queue workers
- Laravel Scheduler
- S3-compatible private storage

Environment defaults should use `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=redis`, and `CACHE_STORE=redis`. Queue workers should be supervised with systemd, Supervisor, or an equivalent process manager.

Create the first super admin with:

```bash
php artisan bough:create-super-admin admin@example.com "Admin Name"
```
