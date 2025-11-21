#!/bin/bash
SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
if [ "$EUID" -ne 0 ]
  then echo "Please run as root"
  exit
fi

# Install java jdk
echo "Running updates"
apt update -yer

echo "Installing Java"
apt install openjdk-17-jdk -y

# add signal-cli user
echo "Adding signal-cli user"
useradd -M signal-cli

# Install signal-cli
#echo "Installing signal-cli"
#ln -sf $1/bin/signal-cli /usr/local/bin/

# The actual download of the signal will be done by the module

#make sure everyone has access to the profile folder
mkdir /var/lib/signal-cli/
chmod -R 777 /var/lib/signal-cli/

#Install the service
cp -f $SCRIPT_DIR/signal-cli-jsonrpc-daemon.service /etc/systemd/system/
cp -f $SCRIPT_DIR/signal-cli-jsonrpc.service /etc/systemd/system/

systemctl daemon-reload

systemctl enable signal-cli-jsonrpc.service
systemctl enable signal-cli-jsonrpc-daemon.service

service signal-cli-jsonrpc start
service signal-cli-jsonrpc-daemon start