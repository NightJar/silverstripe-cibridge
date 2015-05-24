# silverstripe-cibridge
Getting really annoyed with the 'global all the things' structure of code igniter, along with the lack of an autoloader?
People told me to build a bridge and get over it, and I did. Now you can too!

Run an old CI project code directly in SilverStripe (after a few configuration bits) while you re-develop it into something more modern.

##Requirements
* Silverstripe 3.1

##Installation
* Simply drop into silverstripe root (using whatever method)
* Update config
* `dev/build`

I don't recommend mixing CI code with SS code, although it should be (mostly) possible. But don't do it.
I made a small CI application dir clone under my SS project dir, this way things for CI code basically stay the same.

```
mysite/ciport/
mysite/ciport/controllers/
mysite/ciport/libraries/
mysite/ciport/models/
mysite/ciport/views/
```

You might notice the absence of the config dir. This is not accidental. We now get the bonus of the SilverStripe Config system, so we use that (under the CIBridge key).

Now because of the way the CI loader works, we need to tell it where to look for things (models, libraries, even controllers). Take note and see how your project's _config might look:

```yml
CIBridge:
  databases:
    default:
      database: project_dev
      server: "192.168.0.256"
      username: devuser
      password: asdfghjkl
      type: MySQLDatabase
  controllers:
    - mysite/code/ciport/controllers
  views:
    - mysite/code/ciport/views
  helpers:
    - mysite/code/ciport/helpers
  config:
    config_key: "config value"
Session:
  cookie_path: /
```

The cookie path bit isn't necessary, but I needed it, so it might help someone solve an issue around that (at least, much faster than it took me).

##Usage
Well you don't really 'use' it. It's just there, and CI code runs (mostly) fine. If it doesn't, check configuration. If it still doesn't... either update it now or make this project a little more feature complete (then submit a nice PR). Either way it's a big step up.

Don't confuse the aim of the module though. It is NOT to allow one to continue to write CI code. You've moved on, use the bridge to just get over it!

##About
Enjoy the benefits of SilverStripe, such as dependency injection, a more sane logical layout, an awesome Form library, decent templates, etc.

Routing is taken care of on every load, which isn't all that efficient, but it is effective. By defining the controllers dir SS can search through routes for a classname, bringing back route hierarchy via discrete folders.

##Notes
 - **You will need to copy core CI helpers over into your project if you use them** (eg, form_helper). Specify the helper path in the config too.
 - You can specify more than one path for each class type, meaning you can spread your project out into modules, etc. if desired.
 - Procedural files (helpers, views) will be pulled in automatically. Create a _manifest_ignore file to prevent this (good idea for views, not for helpers).
 - It's not 100% feature complete, but gets most of the way there (read: works for me).


 ###TODO
  - Well someone can make it feature complete if they want. I don't recommend it. But PR's are likely to be accepted if someone else thinks it is (and does a nice job of it).
  - Tests might be nice though.