#!/bin/bash

echo "Criando link simbólico do storage..."
php artisan storage:link

echo "Iniciando o Worker da Fila em background..."
php artisan queue:work redis --tries=3 &

echo "Iniciando o Servidor Web..."
php artisan serve --host=0.0.0.0 --port=$PORT
