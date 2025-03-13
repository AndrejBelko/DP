import pandas as pd
import libgeohash as gh
from sqlalchemy import create_engine
import geojson
import csv
import mysql.connector
import sys
import json
from datetime import datetime
import numpy as np
import os

def haversine_vectorized(latitudes, longitudes):
    R = 6371000  # Earth radius in meters
    lat1 = np.radians(latitudes[:-1])
    lat2 = np.radians(latitudes[1:])
    lon1 = np.radians(longitudes[:-1])
    lon2 = np.radians(longitudes[1:])

    d_phi = lat2 - lat1
    d_lambda = lon2 - lon1

    a = np.sin(d_phi / 2.0)**2 + np.cos(lat1) * np.cos(lat2) * np.sin(d_lambda / 2.0)**2
    c = 2 * np.arctan2(np.sqrt(a), np.sqrt(1 - a))
    return np.sum(R * c)

# Get the input CSV path and database name from command-line arguments
file_name = sys.argv[1]
csvPath = sys.argv[2]
dbName = sys.argv[3]
user_id = int(sys.argv[4])
mapmatched = int(sys.argv[5])
type = sys.argv[6]
track_id = sys.argv[7]
timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

print("Reading dataset...")
geohash = []
directory_path = f"/home/data/import/files/db/{dbName}"
# Try to read the existing path CSV to get the max track ID, if not present create a new one
try:
    dx1 = pd.read_csv(f"{directory_path}/{dbName}_path.csv", sep=';')
except FileNotFoundError:
    dx1 = pd.DataFrame(geohash, columns=["user_id","geohash", "track_id", "mapmatched", "type", "timestamp", "length"])
    dx1['track_id'] = dx1['track_id'].astype(int)  # Explicitly cast to int
    if not os.path.isdir(directory_path):
        os.makedirs(directory_path)
    dx1.to_csv(f"{directory_path}/{dbName}_path.csv", index=False, mode="w", sep=';', quoting=csv.QUOTE_NONE)

df = pd.read_csv(csvPath)
df['user_id'] = user_id
df.to_csv(csvPath,index=False, mode="w")
df = df.fillna('')
columns_with_time = [col for col in df.columns if 'date' in col.lower()]

if len(columns_with_time) != 0:
    timestamp = df[columns_with_time[0]].iloc[0]
    if isinstance(timestamp, (int, np.int64)) and len(str(timestamp)) > 10:
        # Convert milliseconds to seconds
        timestamp_in_s = timestamp / 1000

        # Convert to datetime object
        timestamp = datetime.fromtimestamp(timestamp_in_s)
else:
    columns_with_time = [col for col in df.columns if 'time' in col.lower()]
    if len(columns_with_time) != 0:
        timestamp = df[columns_with_time[0]].iloc[0]
        if isinstance(timestamp, (int, np.int64)) and len(str(timestamp)) > 10:
            # Convert milliseconds to seconds
            timestamp_in_s = timestamp / 1000

            # Convert to a datetime object
            dt_object = datetime.utcfromtimestamp(timestamp_in_s)  # Use utcfromtimestamp() for UTC time

            # Format for MySQL
            timestamp = dt_object.strftime('%Y-%m-%d %H:%M:%S')


df['track_id'] = track_id
df['track_id'] = df['track_id'].astype(int)  # Explicitly cast to int
if "lat" in df.columns:
    df.rename(columns={'lat': 'latitude'}, inplace=True)

if "lon" in df.columns:
    df.rename(columns={'lon': 'longitude'}, inplace=True)

if "LATITUDE N/S" in df.columns:
    df.rename(columns={'LATITUDE N/S': 'latitude'}, inplace=True)
    df['latitude'] = df['latitude'].str.slice(0, -1)
    df['latitude'] = df['latitude'].astype(float)

if "LONGITUDE E/W" in df.columns:
    df.rename(columns={'LONGITUDE E/W': 'longitude'}, inplace=True)
    df['longitude'] = df['longitude'].str.slice(0, -1)
    df['longitude'] = df['longitude'].astype(float)
# path.csv

row = 0
total = 0
grouped = df.groupby('track_id')
for track_id, values in grouped:
    # Extract latitudes and longitudes for the track
    latitudes = values['latitude'].to_numpy()
    longitudes = values['longitude'].to_numpy()

    # Calculate trajectory length
    trajectory_length = haversine_vectorized(latitudes, longitudes)

    # Add rows for each point in the track
    for _, row_data in values.iterrows():
        geohash.append([
            user_id,
            gh.encode(row_data['latitude'], row_data['longitude'], precision=7),  # Generate geohash
            track_id,  # Track ID
            mapmatched,  # Mapmatched flag (placeholder)
            type,
            timestamp,  # Current timestamp
            trajectory_length  # Total track length
        ])
        row += 1

    # Write to file in batches
    if row > 1000000:
        dx1 = pd.DataFrame(geohash, columns=["user_id", "geohash", "track_id", "mapmatched", "type", "timestamp", "length"])
        dx1.to_csv(f"{directory_path}/{dbName}_path.csv", header=False, index=False, mode="a", sep=';', quoting=csv.QUOTE_NONE)
        row = 0
        geohash = []
        total += 1

dx1 = pd.DataFrame(geohash, columns=["user_id","geohash", "track_id", "mapmatched", "type", "timestamp", "length"])
dx1.to_csv(f"{directory_path}/{dbName}_path.csv", header=False, index=False, mode="a", sep=';', quoting=csv.QUOTE_NONE)


# --------------------- Track.csv Generation ---------------------

tracks = []

# Try to read the existing track CSV, if not present create a new one
# try:
#     dx = pd.read_csv(f"{directory_path}/{dbName}_track.csv", sep=';')
# except FileNotFoundError:
#     dx = pd.DataFrame(tracks, columns=['route', 'filename', 'track', "mapmatched", "type", "timestamp", "length"])
#     dx.to_csv(f"{directory_path}/{dbName}_track.csv", index=False, mode="w", sep=';', quoting=csv.QUOTE_NONE)

row = 0
total = 0

# Group rows by 'track' and generate GeoJSON
df = pd.read_csv(csvPath)
df['track_id'] = sys.argv[7]
grouped = df.groupby('track_id')
if "lat" in df.columns:
    df.rename(columns={'lat': 'latitude'}, inplace=True)
if "lon" in df.columns:
    df.rename(columns={'lon': 'longitude'}, inplace=True)

if "LATITUDE N/S" in df.columns:
    df.rename(columns={'LATITUDE N/S': 'latitude'}, inplace=True)
    df['latitude'] = df['latitude'].str.slice(0, -1)
    df['latitude'] = df['latitude'].astype(float)

if "LONGITUDE E/W" in df.columns:
    df.rename(columns={'LONGITUDE E/W': 'longitude'}, inplace=True)
    df['longitude'] = df['longitude'].str.slice(0, -1)
    df['longitude'] = df['longitude'].astype(float)

print(f"Generating geojsons for {len(grouped)} tracks into {dbName}_track.csv...")
for track_id, values in grouped:
    trajectory_length = haversine_vectorized(np.array(values['latitude']), np.array(values['longitude']))
    tracks.append([user_id, track_id, file_name, str(geojson.Feature(geometry=geojson.LineString(values[["longitude", "latitude"]].values.tolist()))), mapmatched, type, timestamp, trajectory_length])
    row += 1
    if row > 10000:
        dx2 = pd.DataFrame(tracks, columns=['user_id', 'track_id', 'filename', 'track', 'mapmatched', "type", 'timestamp', "length"])
        #dx.to_csv(f"{directory_path}/{dbName}_track.csv", header=False, index=False, sep=';',
        #          quoting=csv.QUOTE_NONE, mode="a")
        row = 0
        tracks = []
        total += 1
        print(str(total * 10) + "k tracks done...")

# Write the final batch of tracks
dx2 = pd.DataFrame(tracks, columns=['user_id', 'track_id', 'filename', 'track', 'mapmatched', "type", 'timestamp', "length"])
# dx.to_csv(f"{directory_path}/{dbName}_track.csv", header=False, index=False, sep=';', quoting=csv.QUOTE_NONE, mode="a")

print("GeoJSON track generation complete.")

# --------------------- Generating DB Info ---------------------

mapconfig = {"center":{"lat": df["latitude"].median(), "lon": df["longitude"].median()}, "title": dbName, "dbname": dbName, "attribution": ""}


with open(f"/var/www/html/center/{dbName}.json", 'w') as outfile:
    outfile.write(json.dumps(mapconfig))

print(f"DB info written to {dbName}.json.")

print("creating database...")
# Connect to MySQL as 'search' user
mydb = mysql.connector.connect(
    host="localhost",
    user="search",
    password="password"
)
mycursor = mydb.cursor()

# Create the database if it doesn't exist
# mycursor.execute(f"CREATE DATABASE IF NOT EXISTS `{dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;")
mycursor.close()
mydb.close()

# Reconnect to the newly created database as 'root' with local infile enabled
mydb = mysql.connector.connect(
    host="localhost",
    user="root",
    password="root",
    database="hashcode",
    allow_local_infile=True  # Ensure that local_infile is enabled
)
mycursor = mydb.cursor()

# Create 'path' table
# mycursor.execute("DROP TABLE IF EXISTS `path`;")
# mycursor.execute("""
#     CREATE TABLE `path` (
#         `id` int(11) NOT NULL AUTO_INCREMENT,
#         `geohash` varchar(7) NOT NULL,
#         `track` varchar(80) NOT NULL,
#         `mapmatched` varchar(80) NOT NULL,
#         `type` varchar(80) NOT NULL,
#         `timestamp` TIMESTAMP NOT NULL,
#         `length` FLOAT NOT NULL,
#         PRIMARY KEY (`id`),
#         KEY `ihash` (`geohash`)
#     ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;
# """)

sql = """
INSERT INTO path (user_id, geohash, track_id, mapmatched, type, timestamp, length)
VALUES (%s, %s, %s, %s, %s, %s, %s)
"""

# Prepare Data
data = list(dx1[['user_id', 'geohash', 'track_id', 'mapmatched', 'type', 'timestamp', 'length']].itertuples(index=False, name=None))

# Execute Batch Insert
mycursor.executemany(sql, data)
mydb.commit()

sql = """
INSERT INTO tracks (user_id, track_id, filename, track, mapmatched, type, timestamp, length)
VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
"""

# Prepare Data
data = list(dx2[['user_id', 'track_id', 'filename', 'track', 'mapmatched', 'type', 'timestamp', 'length']].itertuples(index=False, name=None))

# Execute Batch Insert
mycursor.executemany(sql, data)
mydb.commit()

# Create 'tracks' table
# mycursor.execute("DROP TABLE IF EXISTS `tracks`;")
# mycursor.execute("""
#     CREATE TABLE `tracks` (
#         `route` int(11) NOT NULL,
#         `filename` varchar(250) NOT NULL,
#         `track` mediumtext NOT NULL,
#         `mapmatched` varchar(80) NOT NULL,
#         `type` varchar(80) NOT NULL,
#         `timestamp` TIMESTAMP NOT NULL,
#          `length` FLOAT NOT NULL,
#         PRIMARY KEY (`route`)
#     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
# """)
#
# # Enable local infile for the session
# mycursor.execute("SET GLOBAL local_infile = 1;")
#
# # Import 'path.csv'
# print("Importing tracks ...")
# try:
#     mycursor.execute(f"""
#         LOAD DATA LOCAL INFILE '{directory_path}/{dbName}_path.csv'
#         INTO TABLE `path`
#         FIELDS TERMINATED BY ';' LINES TERMINATED BY '\n' IGNORE 1 LINES (`id`, `geohash`, `track`, `mapmatched`, `type` , `timestamp`, `length`);
#     """)
# except mysql.connector.Error as err:
#     print(f"Error loading path data: {err}")
#
# # Import 'track.csv'
# print("Importing geojsons ...")
# try:
#     mycursor.execute(f"""
#         LOAD DATA LOCAL INFILE '{directory_path}/{dbName}_track.csv'
#         INTO TABLE `tracks`
#         FIELDS TERMINATED BY ';' LINES TERMINATED BY '\n' IGNORE 1 LINES (`route` ,`filename` ,`track`, `mapmatched`, `type` , `timestamp`, `length`);
#     """)
# except mysql.connector.Error as err:
#     print(f"Error loading track data: {err}")

# Commit and close
mydb.commit()
mycursor.close()
mydb.close()

