#!/data/data/com.termux/files/usr/bin/sh

(
echo "===== ProjectSnapshotBuilder ====="
cat tools/Doctor/Snapshot/ProjectSnapshotBuilder.php

echo
echo "===== ProjectSnapshot ====="
cat tools/Doctor/Snapshot/ProjectSnapshot.php

echo
echo "===== TokenScanner ====="
cat tools/Doctor/Scanners/TokenScanner.php

echo
echo "===== PhpSourceScanner ====="
cat tools/Doctor/Scanners/PhpSourceScanner.php

echo
echo "===== LargestMethodCheck ====="
cat tools/Doctor/Checks/LargestMethodCheck.php

echo
echo "===== DoctorContext ====="
cat tools/Doctor/Context/DoctorContext.php

echo
echo "===== Doctor ====="
cat tools/Doctor/Engine/Doctor.php
) | termux-clipboard-set
