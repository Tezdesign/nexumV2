import sys
import json
import numpy as np
from sklearn.linear_model import LinearRegression

try:
    raw = sys.stdin.read().strip()
    data = json.loads(raw) if raw else []

    if not data:
        print(0.0)
        sys.exit()

    daily = {}

    for entry in data:
        date = entry["date"]
        quantity = int(entry["quantity"])
        daily[date] = daily.get(date, 0) + quantity

    dates = sorted(daily.keys())

    if not dates:
        print(0.0)
        sys.exit()

    x_values = np.array(range(len(dates))).reshape(-1, 1)
    y_values = np.array([daily[date] for date in dates], dtype=float)

    if len(dates) < 3:
        print(round(float(np.mean(y_values)), 2))
        sys.exit()

    model = LinearRegression()
    model.fit(x_values, y_values)

    prediction = model.predict([[len(dates)]])
    print(round(max(0.0, float(prediction[0])), 2))

except Exception as error:
    print("ERROR:", str(error))
