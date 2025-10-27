<?php

namespace App\DataFixtures;

use App\Entity\Provider;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProviderFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Debricked Provider
        $debricked = new Provider();
        $debricked->setCode('debricked');
        $debricked->setName('Debricked');
        $debricked->setConfig([
            'api_url' => 'https://debricked.com/api',
            'supported_languages' => ['java', 'javascript', 'python', 'php', 'go', 'ruby', 'csharp', 'swift'],
            'scan_types' => ['sca', 'vulnerability_scanning', 'license_compliance'],
        ]);
        $manager->persist($debricked);

        // Snyk Provider
        $snyk = new Provider();
        $snyk->setCode('snyk');
        $snyk->setName('Snyk');
        $snyk->setConfig([
            'api_url' => 'https://api.snyk.io',
            'supported_languages' => ['javascript', 'python', 'java', 'ruby', 'go', 'php', 'dotnet'],
            'scan_types' => ['sca', 'sast', 'container', 'iac'],
        ]);
        $manager->persist($snyk);

        $manager->flush();
    }
}
