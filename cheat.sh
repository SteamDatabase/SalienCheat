#!/bin/sh

# VARIABLES
CHEAT_DIR=~/SalienCheat/
TOKENS_FILE=~/tokens.txt
# END VARIABLES

# COLOURS
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m'
# END COLOURS

echo "${YELLOW}SteamDatabase/SalienCheat ${CYAN}Runner${NC}"
echo ""

if [ ! -r $TOKENS_FILE ]
then
    echo "${RED}Unable to read TOKENS_FILE (${YELLOW}${TOKENS_FILE}${RED})${NC}"
    echo "${RED}Closing...${NC}"
    exit 1
fi

if [ ! -d $CHEAT_DIR ]
then
    echo "${RED}Unable to read CHEAT_DIR (${YELLOW}${CHEAT_DIR}${RED})${NC}"
    echo "${RED}Closing...${NC}"
    exit 1
fi

# FUNCTIONS
create_bots()
{
file=$TOKENS_FILE
DIDSTART=false
while IFS= read -r line
do
	if ! screen -list | grep -q $line; then
        DIDSTART=true
        echo "${RED}Bot for ${YELLOW}$line${RED} missing${NC} - creating and starting bot..."
        screen -d -m -S $line bash -c "php ${CHEAT_DIR}cheat.php $line" &
    fi
done <"$file"
if [ "$DIDSTART" = false ] ; then
    echo "${GREEN}Nothing to do${NC}"
fi
}
restart_bots()
{
file=$TOKENS_FILE
while IFS= read -r line
do
	if ! screen -list | grep -q $line; then
        echo "${RED}Bot for ${YELLOW}$line${RED} missing${NC} - creating and starting bot..."
        screen -d -m -S $line bash -c "php ${CHEAT_DIR}cheat.php $line" &
    else
        screen -X -S $line quit &
        wait $!
        screen -d -m -S $line bash -c "php ${CHEAT_DIR}cheat.php $line" &
    fi
done <"$file"
}
# END FUNCTIONS

# Move into SalienCheat dir
cd $CHEAT_DIR

# Check if git pull needed
git fetch origin &
wait $!
UPSTREAM=${1:-'@{u}'}
LOCAL=$(git rev-parse @)
REMOTE=$(git rev-parse "$UPSTREAM")
BASE=$(git merge-base @ "$UPSTREAM")

if [ $LOCAL = $REMOTE ]; then
    echo "${GREEN}Up-to-date${NC}"
    echo "Checking for new tokens to run..."
    echo ""
    create_bots
elif [ $LOCAL = $BASE ]; then
    echo "${YELLOW}Update needed${NC}"
    echo ""
    echo "Running git pull..."
    echo ""
    git pull &
    wait $!
    echo "Restarting bots..."
    restart_bots
fi
