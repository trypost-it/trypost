
## Migration with base seed
```sh
php artisan migrate:fresh --seed
```

# Start queue worker
```sh
php artisan horizon:watch
```

# Share with Ngrok:
```sh
ngrok http --host-header=rewrite trypost.test:443
```