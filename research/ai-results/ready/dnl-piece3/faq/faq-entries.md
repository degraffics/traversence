# FAQ entries: Piece 3 (the admin's Team page and Work queue)

## Can an agent spend money?

No. Every agent's spending limit is $0 a month. None of the Team's steps calls a paid service. A step that would cost more than its agent's limit is refused, and the refusal shows on the ticket's runs.

Each run now records what it cost, so the month's total per agent is a real count. Today every total is $0.

Limits above $0 for agents are **coming**, with the agents' own API budget. Only real people can pay for services, and a real-money payment always needs a person's press.

## What does Undo do on an agent's action?

Every action an agent takes has an Undo. Undo puts things back as if the action never happened:

- a ticket moves back to Planning;
- a build brief is taken back;
- a ticket the audit opened is withdrawn.

An action that can't be undone is never done. The agent can only propose it, and a person accepts or dismisses the proposal.

Each action can be undone once. If two people press Undo at the same moment, only one goes through, and the other is told it's already undone. If an Undo fails, nothing changes, and it can be pressed again.

## What is the before-and-after check?

Before a release goes live, the test accounts look at every page it touches on staging, once before the change and once after. A release that makes a page worse is withdrawn, and the problem goes into the Work queue.

It is built. It is paused for four admin-only releases that no agent built. It is switched back on before any release an agent builds.
