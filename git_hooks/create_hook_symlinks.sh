#!/bin/bash

# Create symlinks in .git/hooks for hook scripts in this directory.
# The script assumes it is executed in the directory where the hook
# scripts are that need to be linked.
# Code is inspired by:
# http://stackoverflow.com/questions/3462955/putting-git-hooks-into-repository

HOOK_NAMES="applypatch-msg pre-applypatch post-applypatch pre-commit prepare-commit-msg commit-msg post-commit pre-rebase post-checkout post-merge pre-receive update post-receive post-update pre-auto-gc"

# Location where git expects the hooks.
HOOK_DIR=$(git rev-parse --show-toplevel)/.git/hooks

# Script location (it's best to make this relative from the HOOK_DIR)
SCRIPT_DIR=../../git_hooks

for hook in $HOOK_NAMES; do

    if [ -x $HOOK_DIR/$SCRIPT_DIR/$hook ]; then
        if [ -a $HOOK_DIR/$hook ]; then
            echo "Hook $hook already exists, skipping!"
            continue
        fi
        # Executable hook script found. Creating a symlink for it.
        cmd="ln -s $SCRIPT_DIR/$hook $HOOK_DIR/$hook"
        echo $cmd
        $cmd
    fi
done
