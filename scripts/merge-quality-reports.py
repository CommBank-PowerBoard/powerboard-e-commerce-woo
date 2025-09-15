#!/usr/bin/env python3
"""
Merge multiple GitLab Code Quality JSON reports into a single report.
Usage: python3 merge-quality-reports.py output.json input1.json input2.json ...
"""
import json
import sys
import os

def merge_quality_reports(output_file, *input_files):
    """Merge multiple GitLab Code Quality JSON reports into one."""
    try:
        all_issues = []
        
        for input_file in input_files:
            if not os.path.exists(input_file):
                print(f'Warning: Input file {input_file} does not exist, skipping...')
                continue
                
            try:
                with open(input_file, 'r', encoding='utf-8') as f:
                    issues = json.load(f)
                
                if isinstance(issues, list):
                    all_issues.extend(issues)
                    print(f'Merged {len(issues)} issues from {input_file}')
                else:
                    print(f'Warning: {input_file} does not contain a valid issue array')
            except Exception as e:
                print(f'Error reading {input_file}: {e}')
                continue
        
        # Remove duplicates based on check_name, description, and location
        unique_issues = []
        seen = set()
        
        for issue in all_issues:
            key = (
                issue.get('check_name', ''),
                issue.get('description', ''),
                issue.get('location', {}).get('path', ''),
                issue.get('location', {}).get('lines', {}).get('begin', 0)
            )
            if key not in seen:
                seen.add(key)
                unique_issues.append(issue)
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(unique_issues, f, indent=2, ensure_ascii=False)
        
        print(f'Generated {len(unique_issues)} total issues (removed {len(all_issues) - len(unique_issues)} duplicates)')
        print(f'Merged code quality report saved to: {output_file}')
        return True
        
    except Exception as e:
        print(f'Error merging reports: {e}')
        import traceback
        print(traceback.format_exc())
        return False

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print('Usage: python3 merge-quality-reports.py output.json input1.json input2.json ...')
        sys.exit(1)
    
    output_file = sys.argv[1]
    input_files = sys.argv[2:]
    
    success = merge_quality_reports(output_file, *input_files)
    sys.exit(0 if success else 1)
