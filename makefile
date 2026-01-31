.PHONY: start test stop logs

start:
	docker-compose up -d --build
	docker-compose exec app composer install
	docker-compose exec app php artisan key:generate || true
	docker-compose exec app php artisan migrate:fresh --seed

test:
	docker-compose exec app php artisan test

stop:
	docker-compose stop

logs:
	docker-compose logs -f
