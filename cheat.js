const fs = require('fs');

const SalienCheat = require('./node/src/index.js');

const token = fs.readFileSync('./token.txt').toString().trim();

if (!token) {
  console.log("You haven't created a token file...");
}

const cheat = new SalienCheat({ token });

cheat.run();
