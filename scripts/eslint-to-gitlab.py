#!/usr/bin/env python3
"""
Convert ESLint JSON report to GitLab Code Quality format.
Usage: python3 eslint-to-gitlab.py input.json output.json
"""
import json
import sys

def convert_eslint_to_gitlab(input_file, output_file):
    """Convert ESLint JSON format to GitLab Code Quality format."""
    try:
        with open(input_file, 'r') as f:
            eslint_data = json.load(f)
        
        issues = []
        for file_data in eslint_data:
            file_path = file_data['filePath']
            
            # Convert absolute path to relative path
            relative_path = file_path
            if file_path.startswith('/'):
                # Find the workspace root and make path relative
                if '/wp-content/plugins/' in file_path:
                    relative_path = file_path.split('/wp-content/plugins/', 1)[1]
                    if '/' in relative_path:
                        relative_path = relative_path.split('/', 1)[1]  # Remove plugin name
                else:
                    # Fallback: use just the filename
                    relative_path = file_path.split('/')[-1]
            
            # Remove leading ./ if present
            if relative_path.startswith('./'):
                relative_path = relative_path[2:]
            
            for message in file_data.get('messages', []):
                issues.append({
                    'type': 'issue',
                    'check_name': message['ruleId'] or 'eslint-error',
                    'description': message['message'],
                    'categories': ['Style'],
                    'severity': 'major' if message['severity'] == 2 else 'minor',
                    'location': {
                        'path': relative_path,
                        'lines': {'begin': message['line']}
                    }
                })
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(issues, f, indent=2, ensure_ascii=False)
        
        print(f'Generated {len(issues)} ESLint issues for Code Quality')
        return True
        
    except Exception as e:
        print(f'Error processing ESLint report: {e}')
        import traceback
        print(traceback.format_exc())
        return False

if __name__ == '__main__':
    if len(sys.argv) != 3:
        print('Usage: python3 eslint-to-gitlab.py input.json output.json')
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    
    success = convert_eslint_to_gitlab(input_file, output_file)
    sys.exit(0 if success else 1)
