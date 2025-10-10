#!/usr/bin/env python3
"""
Convert PHPCS JSON report to GitLab Code Quality format.
Usage: python3 phpcs-to-gitlab.py input.json output.json
"""
import json
import sys

def convert_phpcs_to_gitlab(input_file, output_file):
    """Convert PHPCS JSON format to GitLab Code Quality format."""
    try:
        # Read and clean the JSON data (handle mixed output)
        with open(input_file, 'r') as f:
            content = f.read().strip()

        # Find the JSON part (last line if mixed output)
        lines = content.split('\n')
        json_line = None
        for line in reversed(lines):
            line = line.strip()
            if line.startswith('{') and line.endswith('}'):
                json_line = line
                break

        if not json_line:
            raise ValueError("No valid JSON found in PHPCS report")

        phpcs_data = json.loads(json_line)

        issues = []
        for file_path, file_data in phpcs_data.get('files', {}).items():
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
                    'check_name': message['source'],
                    'description': message['message'],
                    'categories': ['Style'],
                    'severity': 'major' if message['type'] == 'ERROR' else 'minor',
                    'location': {
                        'path': relative_path,
                        'lines': {'begin': message['line']}
                    }
                })

        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(issues, f, indent=2, ensure_ascii=False)

        print(f'Generated {len(issues)} PHPCS issues for Code Quality')
        return True

    except Exception as e:
        print(f'Error processing PHPCS report: {e}')
        import traceback
        print(traceback.format_exc())
        return False

if __name__ == '__main__':
    if len(sys.argv) != 3:
        print('Usage: python3 phpcs-to-gitlab.py input.json output.json')
        sys.exit(1)

    input_file = sys.argv[1]
    output_file = sys.argv[2]

    success = convert_phpcs_to_gitlab(input_file, output_file)
    sys.exit(0 if success else 1)
