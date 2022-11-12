# Getting Away With Murder
_A co-operative role-play game of drama, wit, and mystery._

## GAWM on github
This repo contains an api & browser UI to facilitate remote-play of Getting Away With Murder.
![CI](https://github.com/codemonkey-uk/gawm/workflows/CI/badge.svg?branch=master)

## Comunity

Player community discord: https://discord.gg/d2uMBST

## Privacy

GAWM does not have user accounts, there is no log in, and it does not use cookies. It is our aim to protect the players privacy by design.

When you create a game, you are given an id for that game. When a player joins a game they are also given an id.

The game id and player id are used to communicate player actions to the server. The server stores and sends back the current state of the game.

The actions taken, and any notes written are wiped by an automated process after 2 weeks of inactivity on any game.

Aggregated anonymous statistics about what detail cards are played and discarded are kept so the developers can make informed balance changes to improve the game.

IP addresses are stored temporarily to help prevent misuse of the servers. These are also cleared out automatically after 2 weeks.

The source code can be freely examined here, and you can run the software on your own servers.

## License and Copyright

The content provided with this project (found in the `/assets/` folder) is (c) 2016-2020 Joseph Fowler & Thaddaeus Frogley, and may be distributed as part of the software only, for the purposes of of running the software. All other rights reserved.

The source code (found in the `/src/` folder) is licensed under [AGPL-3.0-or-later](src/LICENSE.txt).

Any libraries included as part of this project (found in `/libs/` folder) are included with their original licences intact.

### Fonts

Special Elite - Copyright (c) 2010 by Brian J. Bonislawsky DBA Astigmatic (AOETI). All rights reserved. Available under the [Apache 2.0 licence](http://www.apache.org/licenses/LICENSE-2.0.html).

Nunito - Copyright 2014 The Nunito Project Authors. Available under the [SIL Open Font License, 1.1](https://scripts.sil.org/cms/scripts/page.php?site_id=nrsi&id=OFL).

