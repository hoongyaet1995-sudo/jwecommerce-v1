"""Pipe one authorized sandbox access token over SSH into local encrypted storage.
No token files or credential stdout are emitted. The provider refresh token stays remote.
"""
import os
import pathlib
import subprocess
import sys
import json

root = pathlib.Path(__file__).resolve().parents[2]
script = root / 'tests/manual/shopee-sandbox-connection-transfer.php'
try:
    remote = subprocess.run(['ssh', '-o', 'BatchMode=yes', 'jw-hostinger',
        'cd /home/u762348670/apps/jwecommerce && JW_SANDBOX_PIPE=1 php'], input=script.read_bytes(), capture_output=True, timeout=90)
    if remote.returncode:
        diagnostic=json.loads(remote.stderr.decode('utf-8').strip())
        raise RuntimeError('Remote sandbox unavailable at '+diagnostic['stage']+' ('+diagnostic['class']+', '+diagnostic.get('reason','unknown')+').')
    # Hosting shells may prepend a login banner. Consume only the expected JSON object.
    wire = remote.stdout.decode('utf-8')
    start = wire.find('{"shop_id":')
    if start < 0:
        flags = [label for label in ['Fatal error','Parse error','Exception','Error','Could not','STDIN','Undefined','Stage'] if label in wire]
        raise RuntimeError('Remote connection envelope missing; bytes='+str(len(remote.stdout))+' diagnostic_tags='+','.join(flags))
    payload, _ = json.JSONDecoder().raw_decode(wire[start:])
    env = dict(os.environ, JW_SANDBOX_PIPE='1')
    local = subprocess.run(['C:/xampp/php/php.exe', str(script), '--import'], input=json.dumps(payload).encode('utf-8'),
        capture_output=True, cwd=root, env=env, timeout=60)
    if local.returncode:
        diagnostic=json.loads(local.stderr.decode('utf-8').strip())
        raise RuntimeError('Local sandbox import failed at '+diagnostic['stage']+' ('+diagnostic['class']+').')
    print(local.stdout.decode('utf-8').strip())
except Exception as error:
    print('Sandbox connection pipe stopped: ' + str(error), file=sys.stderr)
    sys.exit(1)
