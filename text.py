from faker import Faker
import random
from datetime import datetime, timedelta

fake = Faker()

# Sample user IDs from 19 to 32
user_ids = list(range(19, 33))

# Generate 100 food entries
food_entries = []
for _ in range(100):
    food_entries.append(f"""INSERT INTO food (user_id, name, description, quantity, image, latitude, longitude, created_at) VALUES (
        {random.choice(user_ids)},
        '{fake.word().capitalize()} Food',
        '{fake.sentence()}',
        {random.randint(1, 10)},
        'food_image.jpg',
        {round(fake.latitude(), 8)},
        {round(fake.longitude(), 8)},
        '{fake.date_time_between(start_date='-30d', end_date='now').strftime('%Y-%m-%d %H:%M:%S')}'
    );""")

# Generate 20 order entries
order_entries = []
for _ in range(20):
    order_entries.append(f"""INSERT INTO orders (user_id, food_id, quantity, status, created_at) VALUES (
        {random.choice(user_ids)},
        {random.randint(1, 100)},
        {random.randint(1, 5)},
        'pending',
        '{fake.date_time_between(start_date='-20d', end_date='now').strftime('%Y-%m-%d %H:%M:%S')}'
    );""")

# Generate 10 exchange entries
exchange_entries = []
for _ in range(10):
    exchange_entries.append(f"""INSERT INTO exchange (giver_id, receiver_id, item_name, item_description, status, created_at) VALUES (
        {random.choice(user_ids)},
        {random.choice(user_ids)},
        '{fake.word().capitalize()} Item',
        '{fake.sentence()}',
        'completed',
        '{fake.date_time_between(start_date='-15d', end_date='now').strftime('%Y-%m-%d %H:%M:%S')}'
    );""")

# Generate 5 report entries
report_entries = []
for _ in range(5):
    report_entries.append(f"""INSERT INTO reports (reporter_id, reported_user_id, reason, description, created_at) VALUES (
        {random.choice(user_ids)},
        {random.choice(user_ids)},
        'Inappropriate behavior',
        '{fake.paragraph()}',
        '{fake.date_time_between(start_date='-10d', end_date='now').strftime('%Y-%m-%d %H:%M:%S')}'
    );""")

# Generate 30 rating entries
rating_entries = []
for _ in range(30):
    rating_entries.append(f"""INSERT INTO ratings (user_id, rated_user_id, rating, comment, created_at) VALUES (
        {random.choice(user_ids)},
        {random.choice(user_ids)},
        {random.randint(1, 5)},
        '{fake.sentence()}',
        '{fake.date_time_between(start_date='-7d', end_date='now').strftime('%Y-%m-%d %H:%M:%S')}'
    );""")

# Combine all
all_queries = "\n".join(food_entries + order_entries + exchange_entries + report_entries + rating_entries)
all_queries[:3000]  # Show preview of the first part of the SQL string
print(all_queries)