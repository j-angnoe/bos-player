
# Features

## Nice to have


## Todo
- auth services moeten toegankelijk zijn voor alle modules ongeacht
  framework. dus in bos-player
  Sessie wordt mogelijk standaard in redis opgeslagen.
- bos-player moet permissies checken alvorens dispatch naar module.
- navigatie (bos-player) is afhankelijk van permissies.
- wrapLayout afhankelijk van ajax / non ajax calls

- module configuratie
- verschillende partities kunnen dezelfde module gebruiken... kan dat ook zelfde module anders geconfigureerd? goeie vraag.

## Done
- partition/module routing naar een catalogue
- modules kunnen hun eigen framework naar keuze hebben
    (done via ModuleExecutors)
- modules kunnen elkaar extended (kan, laravel-base, modules die als base worden gebruikt moeten zelf maatregelen nemen)
- assets kunnen geserveerd worden, via partition-link of via static link
- verschillende partities kunnen hun eigen set modules hebben.




