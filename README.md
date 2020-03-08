# BOS Player

Ingredienten:
- Omgevingsdefinitie
- Repository vol met modules

En maakt er soep van...

Dus, je hebt een url, zoals klant1.domain.com/partitionId/moduleName/localResourcePath

Stap 1: Resolve het omgevingsbestand

Stap 2: Lees het omgevingsbestand uit
Zet het nodige uit het omgevingsbestand in de omgevingsvariabelen.

Stap 3: Resolve de module en gekenmerkt door moduleName

Stap 4: Geef de controle over aan de module

Stap 5: Afhandeling van de response.

## Voorbeeld:

## Een laravel applicatie als module gebruiken:

Een laravel applicatie kun je aanmaken door bv `laravel new applicatienaam` uit te voeren.
Stap 2: Verwijder o.a. de database credentials uit het .env bestand. We willen namelijk dat
deze environment variabelen door bos-player niet overschreven worden door de dingen die in de .env staan.
Stap 3: Pas public/index.php aan, haal `$kernel->terminate()` weg en vervang `$response->send()` door `$response->sendHeaders(); $response->sendContent();`;

Module is een laravel applicatie

- Zie module laravel-app, vanilla-php en php-frontcontroller

## Resources/assets
Hoe link je naar module resources en assets?
Dit heb je nodig voor bv een SPA enzo.

Het adresseren van resources binnen de module kan op 2 manieren.
Plaats de assets in een folder genaamd `public`, `dist` of `build`
De assets binnen deze folders worden kun je nu op 2 manieren benaderen

GET /partition-name/module-name/path/to/file.txt
GET /module-name/path/to/file.txt

Mocht de directory anders heten dan kun je een `serveFrom` directive
opnemen in de bosModule.json. Voorbeeld:

```
# bosModule.json
{
    "serveFrom" : "assets"
}
```

bij serve-from zal /assets niet in de 
url voor komen.

bijvoorbeeld:
GET http://server.com/env/module/image.png 
wordt gelezen van 
/module/assets/image.png

bij "allowServeFrom" moet /assets wel in de url voorkomen.

GET http://server.com/env/module/assets/image.png
-> module/assets/image.png

## Nu dan het nodige met database

- Allereerst heb ik een database nodig.

- Dan heb ik wat tabelletjes nodig

Dus, stel je wilt een bepaalde module uit laten voeren op een bepaalde database dan kan dat eenvoudig door wat environment-variabelen te prependen aan het php artisan commando...

Vraag: Hoe gaat dit als je meerdere laravel apps hebt die allerlei dingen willen installeren... 

-> Maak module laravel-things
haal de database credentials uit de .env

## Verder bouwen op een reeds gelegde basis

Hiermee kun je voort borduren. Het is van belang
dat de module die je aanwijst maatregelen heeft genomen om dit
gedrag te ondersteunen. De laravel-base is hier een voorbeeld 
van hoe je dat zou kunnen doen.
```
# bosModule.json
{
    "type" : {
        "extends" : "laravel-base"
    }
}
```

## Spelen met auth
Hiervoor een standaard laravel app genomen en de laravel auth preset
toegepast:

```
laravel new laravel-auth
cd laravel-auth
composer require laravel/ui
php artisan ui:auth
```

Wat zou je verder nog willen doen:
- user-manager waarmee je users kunt maken.
- user moet gescheiden worden van inlogmethode, een user
kan met username/password inloggen, via social login (google/facebook/whatever), of via SSO (mits hier vraag naar is)
- een user krijgt permissies binnen een omgeving
- permissies moeten gechecked worden door de bos-player


## Mogelijke strategieen om een catalogus op te bouwen
1. Je begint met een base en al je volgende modules zijn
    allemaal gebasseerd op die base...
    (laravel-base en alles wat daarop volgt). Alle modules
    kunnen nu gebouwd worden met de services die de base-module
    aanbied.

2. Een cross-framework aanpak, bos-player verschaft de diensten
    bos-player wordt verantwoordelijk gemaakt voor het cross-framework
    mogelijk maken van een x aantal diensten, zoals user/session en
    permission checks.

Hierbij komt ook de vraag: Is bos-player verantwoordelijk voor de 
chrome (de layout) of niet..

## De omgevingsmanager
- beschikt over een lijst beschikbare modules (in catalogus)
- open een omgeving (of maak er 1 aan)
    - omgeving aanmaken..
        - waar? map bestand iets?
- selecteer de modules en de partitie/module mapping
- configureer modules voor de omgeving.























