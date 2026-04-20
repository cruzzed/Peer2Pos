---
name: Native Windows stack
description: Peer2Pos runs on native Windows TALL stack — no WSL2; affects printer integration and any OS-level decisions
type: project
---

Peer2Pos is deployed on native Windows (not WSL2). PHP artisan serve runs on Windows natively.

**Why:** Simpler setup, PHP has direct access to Windows hardware (COM ports, USB devices) without usbipd bridging.

**How to apply:** Never suggest WSL2-specific paths (`/dev/usb/lp0`, usbipd-win, CUPS) for hardware integration. For USB printers use `FilePrintConnector('COM3')`. For network printers use `NetworkPrintConnector`. WSL2 notes are archived at `.claude/architecture/archive/wsl2-printing.md`.
