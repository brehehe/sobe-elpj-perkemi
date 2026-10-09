<?php

namespace App\Enums;

enum ParticipantRole: string
{
    case Participant = 'PARTICIPANT';
    case Committee = 'COMMITTEE';
    case Speaker = 'SPEAKER';
    case Finance = 'FINANCE';
    case Observer = 'OBSERVER';
}
