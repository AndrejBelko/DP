import pandas as pd
import numpy as np
from scipy.interpolate import interp1d
import sys

orig_file_path = sys.argv[1]
mm_file_path = orig_file_path.replace("uploads", "mapmatched")

# Load the original trajectory (with timestamps)
original_df = pd.read_csv(orig_file_path)  # Columns: ['timestamp', 'lat', 'lon']

# Load the map-matched trajectory (no timestamps, just lat/lon)
mapmatched_df = pd.read_csv(mm_file_path)  # Columns: ['lat', 'lon']

lat_columns = [col for col in original_df.columns if "lat" in col.lower()]
lon_columns = [col for col in original_df.columns if "lon" in col.lower()]

# Ensure mapmatched data is smaller or equal in size to original
if len(mapmatched_df) > len(original_df):
    raise ValueError("Mapmatched trajectory has more points than the original, which is unexpected.")

# Create a virtual index for both datasets (since no timestamps in mapmatched data)
original_df["index"] = np.linspace(0, 1, len(original_df))  # Normalize original index
mapmatched_df["index"] = np.linspace(0, 1, len(mapmatched_df))  # Normalize mapmatched index

# Interpolation functions for lat/lon
lat_interp = interp1d(mapmatched_df["index"], mapmatched_df["latitude"], kind="linear", fill_value="extrapolate")
lon_interp = interp1d(mapmatched_df["index"], mapmatched_df["longitude"], kind="linear", fill_value="extrapolate")

# Apply interpolation to replace original lat/lon
original_df[lat_columns[0]] = lat_interp(original_df["index"])
original_df[lon_columns[0]] = lon_interp(original_df["index"])

# Drop the index column before saving
original_df.drop(columns=["index"], inplace=True)

# Save the new trajectory with replaced lat/lon
original_df.to_csv(mm_file_path, index=False)

