import csv
import re
import sys
from datetime import date, datetime
from pathlib import Path

import openpyxl


DMS_PATTERN = re.compile(
    r"^\s*(\d+)[°º]\s*(\d+)'\s*([\d.]+)\"\s*([NSEW])\s*$",
    re.IGNORECASE,
)


def decimal_coordinate(value: str) -> float:
    match = DMS_PATTERN.match(str(value))

    if not match:
        raise ValueError(f"Coordenada DMS no válida: {value}")

    degrees, minutes, seconds, direction = match.groups()
    decimal = float(degrees) + float(minutes) / 60 + float(seconds) / 3600

    if direction.upper() in {"S", "W"}:
        decimal *= -1

    return round(decimal, 7)


def iso_date(value) -> str:
    if value in (None, ""):
        return ""
    if isinstance(value, (date, datetime)):
        return value.strftime("%Y-%m-%d")
    return str(value).strip()


def main(source: Path, destination: Path) -> None:
    sheet = openpyxl.load_workbook(source, read_only=True, data_only=True)["TERMINALS"]
    destination.parent.mkdir(parents=True, exist_ok=True)

    with destination.open("w", encoding="utf-8-sig", newline="") as output:
        writer = csv.writer(output)
        writer.writerow([
            "un_location_code",
            "alternative_un_location_code",
            "terminal_code",
            "name",
            "latitude",
            "longitude",
            "valid_from",
            "valid_to",
            "source_version",
        ])

        total = 0
        for row in sheet.iter_rows(min_row=7, values_only=True):
            writer.writerow([
                str(row[0]).strip().upper(),
                str(row[1]).strip().upper() if row[1] else "",
                str(row[2]).strip().upper(),
                str(row[3]).strip(),
                f"{decimal_coordinate(row[5]):.7f}",
                f"{decimal_coordinate(row[6]):.7f}",
                iso_date(row[8]),
                iso_date(row[9]),
                "SMDG-20260609",
            ])
            total += 1

    print(f"Registros convertidos: {total}")
    print(f"Archivo generado: {destination}")


if __name__ == "__main__":
    main(Path(sys.argv[1]), Path(sys.argv[2]))
