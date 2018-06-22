# SalienCheat

👽 Cheating Salien minigame, the proper way.

---

## Usage from GitHub repo

See the instructions in the [root readme](https://github.com/SteamDatabase/SalienCheat)

## Usage as package

Create a file and simply use the following:

```js
const SalienCheat = require('salien-cheat');

const config = {
  token: '', // Your token from https://steamcommunity.com/saliengame/gettoken
};

const cheat = new SalienCheat(config);

cheat.run();
```
