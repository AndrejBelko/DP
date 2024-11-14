import sys
import gpxpy
import csv

# Get the input GPX and output CSV paths from the arguments
gpx_file_path = sys.argv[1]
csv_file_path = sys.argv[2]

# Read the GPX file
with open(gpx_file_path, 'r') as gpx_file:
    gpx = gpxpy.parse(gpx_file)

    # Create the CSV file
    with open(csv_file_path, mode='w', newline='') as csv_file:
        writer = csv.writer(csv_file)
        writer.writerow(['latitude', 'longitude', 'elevation', 'time'])  # Header row

        # Loop through all tracks, segments, and points
        for track in gpx.tracks:
            for segment in track.segments:
                for point in segment.points:
                    writer.writerow([point.latitude, point.longitude, point.elevation, point.time])
