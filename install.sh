#!/bin/sh


echo "Iniciando a instalação CDN-API..." > install.log

read -p "Informe a rota para o composer: " path

if [ "$path" != "" ]
then
    rm install.log

    #rm -Rf vendor/

    composer1="composer update"
    $path$composer1 > install.log

    cp .env.example .env

    php artisan cdn-api:install --database
    php artisan cdn-api:install

else

    echo "Deve informar a rota para o composer e executar novamente o script install.sh"

fi


