#! /usr/bin/env bash
VERSION="$(date "+%Y.%m.%d-%s")"
tar --owner=0 --group=0 --transform 's/^source/unassigned.devices/' -czf "unassigned.devices-$VERSION.tgz" source/

sed '12s/".*"/"'"$(md5sum "unassigned.devices-$VERSION.tgz" | cut -d" " -f1)"'"/' unassigned.devices.plg | sponge unassigned.devices.plg
sed '7s/".*"/"'"$VERSION"'"/' unassigned.devices.plg | sponge unassigned.devices.plg
