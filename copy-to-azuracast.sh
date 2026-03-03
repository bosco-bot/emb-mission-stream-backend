#!/bin/bash
SOURCE_FILE="$1"
DEST_FILE="$2"

if [ -z "$SOURCE_FILE" ] || [ -z "$DEST_FILE" ]; then
    echo "Usage: $0 <source_file> <dest_file>"
    exit 1
fi

if [ ! -f "$SOURCE_FILE" ]; then
    echo "Source file not found: $SOURCE_FILE"
    exit 1
fi

sudo docker cp "$SOURCE_FILE" azuracast:"$DEST_FILE"
if [ $? -eq 0 ]; then
    echo "SUCCESS: File copied to AzuraCast"
else
    echo "ERROR: Failed to copy file"
    exit 1
fi
