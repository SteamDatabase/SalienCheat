const SalienCheat = require('./node/src/index.js');

const config = {
  token: '', // Your token from https://steamcommunity.com/saliengame/gettoken
};

const cheat = new SalienCheat(config);

cheat.run();
