#!/bin/bash

if [ "$EUID" -ne 0 ]
  then echo "Please run as root"
  exit
fi

# Install java jdk
echo "Running updates"
apt update -yser

echo "Installing Java"
apt install openjdk-17-jdk -y


# add signal-cli user
echo "Adding signal-cli user"
useradd -M signal-cli

# Install signal-cli
echo "Installing signal-cli"
ln -sf $1/bin/signal-cli /usr/local/bin/

#make sure everyone has access to the profile folder
mkdir /var/lib/signal-cli/
chmod -R 777 /var/lib/signal-cli/

#Install the service
echo "Installing system service"
$ For DBUS
#cp -f $2/org.asamk.Signal.conf /etc/dbus-1/system.d/
#cp -f $2/org.asamk.Signal.service /usr/share/dbus-1/system-services/
#cp -f $2/signal-cli.service /etc/systemd/system/

#For JSON-RPC
cp -f $2/signal-cli-jsonrpc-daemon.service /etc/systemd/system/
cp -f $2/signal-cli-jsonrpc.service /etc/systemd/system/

systemctl daemon-reload

systemctl enable signal-cli-jsonrpc.service
systemctl enable signal-cli-jsonrpc-daemon.service

systemctl enable signal-cli.service
systemctl reload dbus.service