# Archived: WSL2 Receipt Printer Notes

> Archived 2026-04-19. Peer2Pos runs on native Windows (TALL stack), not WSL2.
> Kept here in case a WSL2 deployment scenario arises in the future.

---

## WSL2 and USB Printers

PHP inside WSL2 **cannot** directly access Windows USB devices. Two solutions:

### Option 1: usbipd-win

Forwards a specific USB device from Windows into WSL2. After forwarding, the printer appears as `/dev/usb/lp0` and `FilePrintConnector('/dev/usb/lp0')` works.

**One-time setup per machine:**
1. Install [usbipd-win](https://github.com/dorssel/usbipd-win) on Windows.
2. In an elevated Windows terminal: `usbipd list` — find the printer's bus ID.
3. `usbipd bind --busid <id>` — share it with WSL2.
4. In WSL2: `usbipd attach --wsl --busid <id>` — printer appears as `/dev/usb/lp0`.
5. `sudo chmod a+rw /dev/usb/lp0` — grant permission to the PHP process user.
6. This `attach` command must be re-run after each Windows reboot; automate it with a Windows startup script or WSL2 `.profile`.

**PHP connector:**
```php
$connector = new FilePrintConnector('/dev/usb/lp0');
```

### Option 2: Run PHP on Windows native

If `php artisan serve` runs natively on Windows (not inside WSL2), `FilePrintConnector('COM3')` works directly. COM port number is found in Windows Device Manager under "Ports (COM & LPT)".

---

## CUPS (Linux / WSL2)

If the printer is attached to a Linux machine or WSL2 with usbipd, configure a CUPS raw queue and print with:

```php
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;

$connector = new CupsPrintConnector('my-receipt-printer');
```

The CUPS queue **must** be configured as "Raw Queue" (no filtering) or use a pass-through PPD. Otherwise CUPS interprets ESC/POS bytes as PostScript and corrupts them.

---

## FilePrintConnector USB vs. Native Windows COM

| Runtime | Path | Notes |
|---|---|---|
| WSL2 + usbipd-win | `/dev/usb/lp0` | Re-attach after every reboot |
| Windows native | `COM3` (varies) | Stable; check Device Manager |
