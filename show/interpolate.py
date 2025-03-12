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

# Normalize indices
original_df["index"] = np.linspace(0, 1, len(original_df))
mapmatched_df["index"] = np.linspace(0, 1, len(mapmatched_df))

# Merge with nearest available points
interpolated_df = pd.merge_asof(original_df, mapmatched_df, on="index", direction="nearest")

# Drop extra columns and save
interpolated_df.drop(columns=["index"], inplace=True)
interpolated_df.to_csv(mm_file_path, index=False)

