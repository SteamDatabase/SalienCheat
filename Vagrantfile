# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.define :ubuntu do |box|
    box.vm.box = "bento/ubuntu-18.04"
    box.vm.host_name = 'salien-cheat-box'
    box.vm.synced_folder ".", "/home/vagrant/salien"

    box.vm.provision "shell", inline: <<-SHELL
      set -o xtrace
      apt-get update
      apt-get install -y php-cli php-curl python-pip python3-pip
      pip2 install requests tqdm
      pip3 install requests tqdm
    SHELL

    box.vm.provision "shell", privileged: false, inline: <<-SHELL
        ln -s /home/vagrant/salien/cheat.php
        ln -s /home/vagrant/salien/cheat.py
        ln -s /home/vagrant/salien/token.txt
    SHELL
  end
end
