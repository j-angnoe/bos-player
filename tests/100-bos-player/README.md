# Basic functionality

We have the bos-player, which accepts --catalogue and an --environment as options.

A module is a folder inside the catalogue directory and should contain
a bosModule.json file.

Each environment bos-player serves is defined by an environment.json file.
The environment.json determines which modules gets served via bos-player.
The environment.json also contains environment variables that will be made
available to the module, as well as module specific settings.


## Example bosModule.json
```json
{
    "name" : "String, required",
    "description" : "String, optional description",
    "type" : "String|Object, ModuleType"
}
```

## Example environment.json 
```json
{
    "modules" : [
        { 
            "id" : "module-id, points to a folder inside our catalogue directory",
            "settings" : {
                "some_setting" : "some_value"
            }
        },
        {
            "id" : "another module id",
        }
    ]
}
```

## More
Besides testing these basic functionalities,
we also test the testing utilities inside `../includes.php`:

start_webserver(catalogue, environmentFileOrDir):
     should start a bos-player webserver and return a pid for us.

curl(url):
    runs a curl request against the most recently started webserver.

assertCurl(requestPath, assertionStringOrArray):
    runs a curl request and assert that a string (or multiple strings) are
    seen in the output.

