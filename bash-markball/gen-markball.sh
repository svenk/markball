#!/bin/bash
#
## Exemplary bash script to create a Markball file and upload it
## somewhere for convenience.
#

# some file where output is stored to:
output="output.txt"

echo "Writing output to $output"
echo -n > $output

# self documenting: if no parameter given
#if [ $# -eq 0 ]; then grep -E '^##' "$0"; exit; fi

have() { which $@ 2>/dev/null >/dev/null; } # checks on the path, not bash builtins

hiddenexec() { $@ >> $output 2>&1; } # execute something and put all output into output
log() { hiddenexec echo $@; } # log something

# semantics of our funny minimalistic markup language
markup() { log "#{$1} ${@:2}"; }
heading() { markup "HEAD" $@; }
logcmd() { markup "CMD" $@; }
highlight() { markup "HIGHLIGHT" $@; }
logitem() { markup "ITEM" $@; } # list item
spacing() { log; } # just some lines of spacing
beginbracket() { log "#{BEGIN} $@"; }
endbracket() { log "#{END} $@"; }
bracketexec() { beginbracket $@; hiddenexec $@; endbracket $@; }

# composita
verbose() { logcmd $@; bracketexec $@; } # verbose a command and execute it
log_file() { bracketexec cat $@; } # log a text file

heading "Welcome to a Markdown file generation in bash"
log "This is ordinary text, created at $(hostname) at $(date)."
log "This is just another line of text."
spacing
log "So let's check what you have in your environment:"
spacing
verbose env
spacing

heading "Show my code"
log "So what generated this stuff?"
verbose cat $0

uploadWith() {
	echo "Uploading the file ${output} to:"
	cat $output | $@ || { echo "Failure with uploading! Please just look up the file $PWD/${output}."; }
}

if have curl; then
	uploadWith 'curl -s -F sprunge=<- http://sprunge.us'
elif have nc; then
	uploadWith 'nc termbin.com 9999'
else
	echo "See the output at ${output}."
fi

