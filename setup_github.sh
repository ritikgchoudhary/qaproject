#!/bin/bash
# Script to connect to GitHub repository

echo "=== GitHub Repository Setup ==="
echo ""

# Check if remote already exists
if git remote | grep -q origin; then
    echo "Remote 'origin' already exists:"
    git remote -v
    echo ""
    read -p "Do you want to update it? (y/n): " update_remote
    if [ "$update_remote" = "y" ]; then
        read -p "Enter GitHub repository URL (e.g., https://github.com/username/repo.git): " repo_url
        git remote set-url origin "$repo_url"
        echo "✅ Remote URL updated!"
    fi
else
    read -p "Enter GitHub repository URL (e.g., https://github.com/username/repo.git or git@github.com:username/repo.git): " repo_url
    
    if [ -z "$repo_url" ]; then
        echo "❌ Repository URL is required!"
        exit 1
    fi
    
    git remote add origin "$repo_url"
    echo "✅ Remote 'origin' added!"
fi

echo ""
echo "Current remote configuration:"
git remote -v

echo ""
read -p "Do you want to push to GitHub now? (y/n): " push_now

if [ "$push_now" = "y" ]; then
    echo ""
    echo "Pushing to GitHub..."
    
    # Check current branch
    current_branch=$(git branch --show-current)
    echo "Current branch: $current_branch"
    
    # Push to GitHub
    git push -u origin "$current_branch"
    
    if [ $? -eq 0 ]; then
        echo "✅ Successfully pushed to GitHub!"
    else
        echo "❌ Push failed. Please check your GitHub credentials and repository permissions."
        echo ""
        echo "If you need to set up authentication:"
        echo "1. For HTTPS: Use personal access token"
        echo "2. For SSH: Set up SSH keys"
    fi
fi

echo ""
echo "=== Setup Complete ==="
