#!/usr/bin/env python3

import subprocess
import sys
import os
import json

def run_command(cmd, description, allow_failure=True):
    """Run a command and handle output/errors"""
    print(f"=== {description} ===")
    try:
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
        if result.stdout:
            print(result.stdout)
        if result.stderr:
            print(f"STDERR: {result.stderr}")
        if result.returncode != 0 and not allow_failure:
            print(f"Command failed with code {result.returncode}")
            sys.exit(1)
        return result.returncode == 0
    except Exception as e:
        print(f"Error running command: {e}")
        if not allow_failure:
            sys.exit(1)
        return False

def get_file_size(filename):
    """Get file size safely"""
    try:
        return os.path.getsize(filename)
    except:
        return 0

def count_json_issues(filename):
    """Count issues in JSON file"""
    try:
        with open(filename, 'r') as f:
            data = json.load(f)
            return len(data)
    except:
        return 0

def main():
    print("=== Combined Code Quality Job ===")

    # Install dependencies
    run_command("composer install", "Installing Composer dependencies", allow_failure=False)
    run_command("npm ci", "Installing NPM dependencies", allow_failure=False)

    # Run PHPCS - capture exit code but continue to generate reports
    print("=== Running PHPCS ===")
    phpcs_result = subprocess.run("./vendor/bin/phpcs --standard=phpcs.xml --report=summary includes/ tests/unit/", shell=True, capture_output=True, text=True)
    phpcs_has_issues = phpcs_result.returncode != 0

    if phpcs_result.stdout:
        print(phpcs_result.stdout)
    if phpcs_result.stderr:
        print(f"PHPCS STDERR: {phpcs_result.stderr}")

    # Generate PHPCS JSON report regardless of issues
    run_command("./vendor/bin/phpcs --standard=phpcs.xml --report=json includes/ tests/unit/ 2>/dev/null > phpcs-report.json || true", "Generating PHPCS JSON Report", allow_failure=True)
    phpcs_size = get_file_size("phpcs-report.json")
    print(f"PHPCS completed. Report size: {phpcs_size} bytes")

    # Run ESLint - capture exit code but continue to generate reports
    print("=== Running ESLint ===")
    eslint_result = subprocess.run("npm run lint:js", shell=True, capture_output=True, text=True)
    eslint_has_issues = eslint_result.returncode != 0

    if eslint_result.stdout:
        print(eslint_result.stdout)
    if eslint_result.stderr:
        print(f"ESLint STDERR: {eslint_result.stderr}")

    # Generate ESLint JSON report regardless of issues
    run_command("npm run lint:js -- --format=json --output-file=eslint-report.json || true", "Generating ESLint JSON Report", allow_failure=True)
    eslint_size = get_file_size("eslint-report.json")
    print(f"ESLint completed. Report size: {eslint_size} bytes")

    # Convert to GitLab format
    run_command("python3 scripts/phpcs-to-gitlab.py phpcs-report.json phpcs-gl-code-quality-report.json || true", "Converting PHPCS to GitLab format", allow_failure=True)
    run_command("python3 scripts/eslint-to-gitlab.py eslint-report.json eslint-gl-code-quality-report.json || true", "Converting ESLint to GitLab format", allow_failure=True)

    # Merge reports
    run_command("python3 scripts/merge-quality-reports.py gl-code-quality-report.json phpcs-gl-code-quality-report.json eslint-gl-code-quality-report.json || true", "Merging Reports", allow_failure=True)

    # Final results and determine if pipeline should fail
    print("=== Final Results ===")
    total_issues = 0

    if os.path.exists("gl-code-quality-report.json"):
        final_size = get_file_size("gl-code-quality-report.json")
        issue_count = count_json_issues("gl-code-quality-report.json")
        total_issues = issue_count
        print(f"Final report size: {final_size} bytes")
        print(f"Issues found: {issue_count}")
    else:
        print("❌ No final report created, creating empty one")
        with open("gl-code-quality-report.json", "w") as f:
            json.dump([], f)

    # Determine pipeline result
    has_quality_issues = phpcs_has_issues or eslint_has_issues or total_issues > 0

    if has_quality_issues:
        print("❌ Quality issues detected - failing pipeline!")
        print(f"PHPCS issues: {'Yes' if phpcs_has_issues else 'No'}")
        print(f"ESLint issues: {'Yes' if eslint_has_issues else 'No'}")
        print(f"Total issues in report: {total_issues}")
        sys.exit(1)
    else:
        print("✅ No quality issues found - pipeline passes!")

    print("=== Quality Scan Complete ===")

if __name__ == "__main__":
    main()
