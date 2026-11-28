<?php
// ANCHOR: replaces         ['someone-hurt', 'Someone is hurt or very sick', 'urgent', 1, 'resident', through              ['Pharmacies', 'resident', 'Pharmacies|Pharmacists', '', 'listing', 'phone']]],
// (inside const SEED: the health block from Someone is hurt through Feeling sick; the first matching last line after the first line)
// What changed:
// - "urgent care", "walk in clinic" leave Feeling sick (normal) for a new situation, Urgent care (urgent): read as urgent,
//   so known-closed answers are left out and open now leads (audit 2026-11-27 F10).
// - Each "urgent care" need names Urgent Care% first and carries the word "urgent", so solutions() puts urgent care ahead
//   of other clinics (audit C7).
        ['someone-hurt', 'Someone is hurt or very sick', 'urgent', 1, 'resident',
            ['someone is hurt', 'badly hurt', 'bleeding badly', 'wont stop bleeding', 'chest pain', 'heart attack', 'cant breathe', 'not breathing', 'overdose', 'poisoned', 'snake bite', 'stroke', 'unconscious', 'passed out', 'seizure', 'head injury'],
            [['Emergency room and hospitals', 'resident', 'Hospitals|Emergency Medical%|Medical Centers', 'emergency', 'listing', 'phone'],
             ['Ambulance', 'resident', 'Ambulance Service|Air Ambulance Service|Fire Departments', '', 'listing', 'phone'],
             ['Urgent care and clinics', 'resident', 'Urgent Care%|Clinics|Ambulatory Health Care Services|Health Services', 'urgent', 'listing', 'phone']]],
        ['injury', 'An injury', 'urgent', 0, 'resident',
            ['broke my', 'broken my', 'broke my arm', 'broke my leg', 'broke my ankle', 'broke my wrist', 'broke my foot', 'broke my hand', 'broke my finger', 'broke my toe',
             'broke my nose', 'broke my rib', 'broken bone', 'broken arm', 'broken leg', 'broken ankle', 'broken wrist', 'sprained', 'sprained my ankle', 'twisted my ankle', 'twisted my knee',
             'hurt my back', 'hurt my knee', 'hurt my', 'injured', 'injury', 'cut myself', 'need stitches', 'deep cut', 'dislocated', 'fell and hurt', 'bad burn'],
            [['Urgent care and clinics', 'resident', 'Urgent Care%|Clinics|Ambulatory Health Care Services|Health Services|Medical Centers', 'urgent', 'listing', 'phone'],
             ['Emergency room and hospitals', 'resident', 'Hospitals|Emergency Medical%', 'emergency', 'listing', 'phone'],
             ['Bone and joint doctors', 'resident', 'Orthopedic Surgeons|Physicians & Surgeons', '', 'listing', 'phone'],
             ['Braces, crutches and supplies', 'resident', 'Pharmacies|Home Health & Health Care Equipment', '', 'listing', 'where'],
             ['Getting better', 'resident', 'Physical Therapists|Chiropractors DC|Occupational Therapists', '', 'listing', 'phone']]],
        ['feel-sick', 'Feeling sick', 'normal', 0, 'resident',
            ['feel sick', 'im sick', 'fever', 'flu', 'sore throat', 'need a doctor', 'see a doctor'],
            [['Clinics and urgent care', 'resident', 'Urgent Care%|Clinics|Ambulatory Health Care Services|Health Services|Medical Centers', 'urgent', 'listing', 'phone'],
             ['Doctors', 'resident', 'Physicians & Surgeons|Nurses-Practitioners|Physicians Assistants', '', 'listing', 'phone'],
             ['Pharmacies', 'resident', 'Pharmacies|Pharmacists', '', 'listing', 'phone']]],
        // "urgent care" is a need that can't wait (decisions/0181; the listing audit 2026-11-27, F10 and C7): urgent, so open
        // now leads and what's known to be closed isn't offered; urgent care itself before other clinics. Not a danger
        // situation (no 911 line): that list is Admin's (principle 6).
        ['urgent-care', 'Urgent care', 'urgent', 0, 'resident',
            ['urgent care', 'urgent care clinic', 'urgent care center', 'urgentcare', 'walk in clinic', 'walk in care', 'emergency room', 'emergency department'],
            [['Urgent care and walk-in clinics', 'resident', 'Urgent Care%|Clinics|Ambulatory Health Care Services|Medical Centers', 'urgent walk', 'listing', 'phone'],
             ['Emergency room and hospitals', 'resident', 'Hospitals|Emergency Medical%|Medical Centers', 'emergency', 'listing', 'phone']]],
