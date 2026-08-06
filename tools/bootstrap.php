<?php

declare(strict_types=1);

require_once __DIR__ . '/Doctor/Contracts/CheckInterface.php';

require_once __DIR__ . '/Doctor/DTO/CheckResult.php';
require_once __DIR__ . '/Doctor/DTO/DoctorResult.php';

require_once __DIR__ . '/Doctor/Engine/FileCounter.php';

require_once __DIR__ . '/Doctor/Engine/Doctor.php';
require_once __DIR__ . '/Doctor/Engine/DoctorRunner.php';

require_once __DIR__ . '/Doctor/Checks/ProjectStatisticsCheck.php';
require_once __DIR__ . '/Doctor/Checks/LargestControllerCheck.php';
require_once __DIR__ . '/Doctor/Checks/LargestServiceCheck.php';
require_once __DIR__ . '/Doctor/Checks/EmptyDirectoryCheck.php';
require_once __DIR__ . '/Doctor/Checks/DomainMigrationCheck.php';
require_once __DIR__ . '/Doctor/Checks/LegacyFileCheck.php';
require_once __DIR__ . '/Doctor/Checks/FoundationUsageCheck.php';

require_once __DIR__ . '/Doctor/Output/ConsoleRenderer.php';
