#!/bin/bash
set -e

echo "Switching to sync-advance-impact..."
git checkout sync-advance-impact

echo "Merging latest main into sync-advance-impact..."
git merge main --no-edit

echo "Pushing to advance-impact's main..."
git push advance-impact sync-advance-impact:main

echo "Switching back to main..."
git checkout main

echo "Done! nhef-advance-impact is now up to date."