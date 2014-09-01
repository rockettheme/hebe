# Welcome to hebe!
`hebe` is an internal tool that we use at [RocketTheme][rockettheme] to help create easy symbolic links to our projects and setup environments quickly.

Every projects that has a `hebe.json` manifest can be _registered_ and then linked whenever needed. Hebe itself is registrable.

The reason we decided to create `Hebe` is because often times we have projects that require many bits to be symbolicly linked into different locations and in order to keep centralized all the changes we apply. Performing this operation manually is no fun and since we created `hebe`, we speed up our process of getting started dramatically.


# Installation
You can install hebe by running the one-line command below, making sure you change directory to the folder you actually want hebe to get cloned to. By default it gets installed at the root of your `$HOME` directory.

```bash
cd ~ && git clone https://github.com/rockettheme/hebe.git &&  cd hebe && ./hebe register . && ./hebe link hebe /usr/local/bin && echo -e "Installation completed\n\n Hebe help:" && hebe
```

Now you are all set with hebe, you can run `hebe` from anywhere in your terminal and `hebe help` to get a list of commands.


# Update
You can easily update hebe from anywhere by running the command
```bash
hebe update
```


# How does it work
For instance, let's say you are working on a component for Joomla, that has also a module, a plugin and a library.

```
~/MyProject
    |-- component
    |   |-- admin
    |   `-- site
    |-- library
    |-- module
    `-- plugin
```

To test this setup on a Joomla instance you'll have to link every bit manually to different locations:

```
component/admin => /administrator/components/com_myproject
component/site  => /components/com_myproject
library         => /libraries/myproject
module          => /modules/mod_myproject
plugin          => /plugins/system/myproject
```

This takes quite some time, especially if you want different instances.

With hebe, all this is simplified by one line command. Once you have defined your `hebe.json` manifest and registered it, all you need to do is link the project:

```bash
$ hebe link myproject ~/Sites/joomla_instance
```

And this is how the `hebe.json` manifest would look like:

```
{
    "project": "MyProject",
    "platforms": {
        "joomla3": {
            "nodes": {
                "com_myproject": [
                    {
                        "source": "/component/admin",
                        "destination": "/administrator/components/com_myproject"
                    },
                    {
                        "source": "/component/site",
                        "destination": "/components/com_myproject"
                    }
                ],
                "library": [
                    {
                        "source": "/library",
                        "destination": "/libraries/myproject"
                    }
                ],
                "mod_myproject": [
                    {
                        "source": "/module",
                        "destination": "/modules/mod_myproject"
                    }
                ],
                "plg_system_myproject": [
                    {
                        "source": "/plugin",
                        "destination": "/plugins/system/myproject"
                    }
                ]
            }
        }
    }
}
```


# Registered projects
Whenever you register a project it gets stored in a local file at `~/.hebe/projects`. You can edit this file manually and next time you run hebe it will pick up your changes.

# License
[LICENSE](LICENSE)


# Authors
[RocketTheme Team][rockettheme]


[rockettheme]: http://www.rockettheme.com
