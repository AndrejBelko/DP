import sys

import gpxpy
import csv
import xml.etree.ElementTree as ET

gpx_file_path = sys.argv[1]
csv_file_path = sys.argv[2]

# Parse XML from file manually
with open(gpx_file_path, 'rb') as raw_gpx:
    tree = ET.parse(raw_gpx)

root = tree.getroot()

namespaces = {
    'default': 'http://www.topografix.com/GPX/1/1',
    'ns3': 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1',
    'gpxtpx': 'http://www.garmin.com/xmlschemas/TrackPointExtension/v2'
}

trkpts = root.findall(".//default:trkpt", namespaces)

# Parse with gpxpy
with open(gpx_file_path, 'r', encoding='utf-8') as gpx_file:
    gpx = gpxpy.parse(gpx_file)

    with open(csv_file_path, mode='w', newline='') as csv_file:
        writer = csv.writer(csv_file)
        writer.writerow(['latitude', 'longitude', 'time', 'height', 'speed', 'hr'])

        index = 0
        for track in gpx.tracks:
            for segment in track.segments:
                for point in segment.points:
                    speed_kmh = None
                    hr = None

                    if index < len(trkpts):
                        trkpt = trkpts[index]
                        ext = trkpt.find(".//ns3:TrackPointExtension", namespaces)

                        if ext is not None:
                            speed_el = ext.find("ns3:speed", namespaces)
                            hr_el = ext.find("ns3:hr", namespaces)

                            if speed_el is not None:
                                try:
                                    speed_kmh = float(speed_el.text) * 3.6  # convert m/s → km/h
                                except (ValueError, TypeError):
                                    pass

                            if hr_el is not None:
                                try:
                                    hr = int(hr_el.text)
                                except (ValueError, TypeError):
                                    pass

                        # Try gpxtpx:hr if ns3:hr was not found
                        if hr is None:
                            ext2 = trkpt.find(".//gpxtpx:hr", namespaces)
                            if ext2 is not None:
                                try:
                                    hr = int(ext2.text)
                                except (ValueError, TypeError):
                                    pass

                    writer.writerow([
                        point.latitude,
                        point.longitude,
                        point.time,
                        point.elevation,
                        round(speed_kmh, 2) if speed_kmh is not None else '',
                        hr if hr is not None else ''
                    ])

                    index += 1
