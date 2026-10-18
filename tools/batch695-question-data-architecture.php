<?php

declare(strict_types=1);

$q = static fn(string $stem, string $answer, array $distractors, string $explanation, string $competency, string $scenario): array => [
    'difficulty'=>'medium', 'question'=>$stem, 'answer'=>$answer, 'distractors'=>$distractors,
    'explanation'=>$explanation, 'tested_competency'=>$competency, 'scenario'=>$scenario,
];

return [
    'arch-bp344-continuous-accessible-route'=>[
        $q('A planned accessible route to a public learning room crosses a short change in level that is currently drawn as a step. What is the strongest design response?','Provide an applicable step-free transition and verify the full route and relevant ramp or mechanical-access provisions in the current BP 344 rules.',['Keep the step and add a sign telling users to request assistance.','Rely on a portable board without reviewing its suitability or route conditions.','Move the room label while leaving the level change on the accessible path.'],'The 2024 BP 344 IRR states that level changes require a ramp except where specified alternatives serve them. A route review must check the whole path and the exact applicable conditions.','Apply current accessibility requirements to an interrupted route.','step-interrupts-route'),
    ],
    'arch-bp344-grating-and-cover-detail'=>[
        $q('A floor grate on an accessible path is level with the adjacent finish and slip-resistant, but the gaps measure 16 mm. What revision addresses the identified deficiency?','Specify a grate with spacing no greater than 13 mm and verify levelness and slip resistance after installation.',['Keep the grate because levelness and slip resistance satisfy every requirement.','Cover the grate with a loose mat that can shift during use.','Move the grate to another part of the accessible path without checking its opening size.'],'The current NCDA rule limits spacing between grates to 13 mm; the other features remain separate checks.','Apply the current grate-opening requirement and verify the assembly.','grate-spacing'),
    ],
    'arch-bp344-services-away-from-routes'=>[
        $q('An electrical outlet and temporary cable are shown across a required accessible corridor. What is the most appropriate coordination action?','Reroute the wiring and locate the outlet away from the accessible path, then verify the corridor remains free of trip hazards.',['Add a floor sign and retain the cable crossing as drawn.','Ask building users to lift the cable when passing.','Keep the layout because the outlet is needed near the corridor.'],'The 2024 BP 344 IRR says wiring and outlets must not create trip hazards and should be positioned away from accessible paths and corridors.','Coordinate electrical services away from accessible circulation.','wiring-crosses-corridor'),
    ],
    'arch-ra9266-utility-systems-coordination'=>[
        $q('During design review, mechanical, electrical, sanitary, and plumbing routes conflict with a clear circulation path. Which response best reflects the architect’s scope and coordination role?','Coordinate a revised utility layout with the relevant specialists and verify that system performance and accessible circulation are preserved.',['Leave each system where drawn because only contractors resolve design conflicts.','Remove the circulation requirement to make room for every utility route.','Select a utility route without consulting the responsible specialist.'],'RA 9266 includes utility system layouting in architecture services. Resolving interfaces requires coordination with the relevant technical professionals and applicable rules.','Coordinate building systems within architecture practice.','utility-circulation-conflict'),
    ],
];
