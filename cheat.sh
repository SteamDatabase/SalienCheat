#!/bin/bash
# Little Bash Script for SalienCheat
#
# Zuko
# v1.0.0  25.06.2018

SCRIPT_DIR=$(dirname "$(which "$0")") # readlink -f # pwd
ACCOUNTS_CONF_FILE="accounts.txt"

## Functions
# Check for executables
# Params:
# Executable name/s = $1
function checkExecutables {
	local EXTNAME MISSINGEXT
	EXTNAME="${1}"

	for name in ${EXTNAME}; do
		if ! type "${name}" > /dev/null 2>&1; then
		MISSINGEXT+=("${name}")
		fi
	done

	if [[ ${#MISSINGEXT[@]} -ne "0" ]]; then
		echo -e " ### Please install: \"${MISSINGEXT[*]}\""
		exit 1
	fi
}

# ;-)
function cText {
	case $1 in
		W) echo -e "\033[0m$2\033[0m" ;;
		G) echo -e "\033[32m$2\033[0m" ;;
		B) echo -e "\033[34m$2\033[0m" ;;
		R) echo -e "\033[31m$2\033[0m" ;;
		Y) echo -e "\033[33m$2\033[0m" ;;
	esac
}

# Config File?
function checkConfigFile {
	if [[ -s "${SCRIPT_DIR}/${ACCOUNTS_CONF_FILE}" ]]; then
		# TODO Check if file is OK?
		return 0
    else
		# No file or it's empty
		echo -e " ### Please add your tokens to \"${SCRIPT_DIR}/${ACCOUNTS_CONF_FILE}\""
		exit 1 # Bye ;(
	fi
}

# Update (you must install SalienCheat manually first)
function updateScript {
	local LOCAL REMOTE UPSTREAM
	git remote update > /dev/null 2>&1
	UPSTREAM=${1:-'@{u}'}
	LOCAL=$(git rev-parse @{0})
	REMOTE=$(git rev-parse "${UPSTREAM}")

	if [[ "${LOCAL}" == "${REMOTE}" ]]; then
		cText "G" "Already up-to-date…"
		exit
	else
		cText "G" "Updating…"
		git pull

		cText "G" "Restart…"
		restartScript
    fi
}

# Run (in screen)
function startScript {
	cText "G" "Starting…"
	if checkConfigFile; then
		# Start separate script in "screen" for all tokens
		while read NAME TOKEN; do
			# "Screen's" already running?
			if ! screen -S "salien-${NAME}" -X select . > /dev/null 2>&1; then
			# Run new one ;)
			cText "B" " - Starting: $(cText "Y" "${NAME^^}")"
			screen -S "salien-${NAME}" -dm bash -c "php ${SCRIPT_DIR}/cheat.php ${TOKEN}"
			sleep 1 # Wait a sec
			else
				cText "B" " - Already Running: $(cText "Y" "${NAME^^}")"
			fi
		done < "${SCRIPT_DIR}/${ACCOUNTS_CONF_FILE}"
	fi
}

# Restart
# Params:
# Restart/Stop [0/1] = $1
function restartScript {
	local PARAM="${1}"

	if [[ "${PARAM}" -eq "0" ]]; then
		cText "G" "Restarting…"
	else
		cText "G" "Stopping…"
	fi

	if checkConfigFile; then
	# Kill every "living" screen!
		while read NAME TOKEN; do
			# Kill!
			if screen -S "salien-${NAME}" -X select . > /dev/null 2>&1; then
				cText "R" " - Killing: $(cText "Y" "${NAME^^}")"
				screen -X -S "salien-${NAME}" kill
			else
				cText "R" " - Already Stopped: $(cText "Y" "${NAME^^}")"
			fi
			if [[ "${PARAM}" -eq "0" ]]; then
				sleep 1 # Wait a sec
				# Run new one ;)
				cText "B" " - Starting: $(cText "Y" "${NAME^^}")"
				screen -S "salien-${NAME}" -dm  bash -c "php \"${SCRIPT_DIR}/cheat.php\" ${TOKEN}"
			fi
		done < "${SCRIPT_DIR}/${ACCOUNTS_CONF_FILE}"
	fi
}

checkExecutables "git php screen"

function usageText {
	echo -e "Little Bash script for SalienCheat\n"

	echo -e "Options:"
	echo -e "\t-update  [--update]\t- Update! (+restart)"
	echo -e "\t-start   [--start]\t- Start the Fight!"
	echo -e "\t-stop    [--stop]\t- Stop!"
	echo -e "\t-restart [--restart]\t- Restart!"
}

if [ $# -gt "0" ]; then
	while [ -n "$1" ]; do
		case $1 in
			-update|--update)
				updateScript ;;
			-restart|--restart)
				restartScript "0" ;;
			-start|--start)
				startScript ;;
			-stop|--stop)
				restartScript "1" ;;
			*|-h|--help)
				usageText; exit ;;
		esac
		shift
	done
else
	usageText; exit
fi

# EOF
