import pandas as pd
import xgboost as xgb
import joblib
from sdv.metadata import SingleTableMetadata
from sdv.single_table import GaussianCopulaSynthesizer
from sklearn.model_selection import train_test_split, GridSearchCV
from sklearn.preprocessing import OneHotEncoder
from sklearn.compose import ColumnTransformer
from sklearn.pipeline import Pipeline
from sklearn.metrics import classification_report
import warnings
import os

def train():
    """
    This function runs the full model training pipeline locally and saves
    the final model and preprocessor files.
    """
    warnings.filterwarnings('ignore')
    print("---- Model Training Started ----")

    # --- Step 1: Generate Synthetic Data ---
    print("\n[1/5] Generating synthetic data...")
    metadata = SingleTableMetadata()
    metadata.add_column(column_name='transaction_id', sdtype='id')
    metadata.add_column(column_name='account', sdtype='id')
    metadata.add_column(column_name='amount', sdtype='numerical', computer_representation='Float')
    metadata.add_column(column_name='currency', sdtype='categorical')
    metadata.add_column(column_name='timestamp', sdtype='datetime', datetime_format='%Y-%m-%d %H:%M:%S')
    metadata.add_column(column_name='is_fraud', sdtype='boolean')
    synthesizer = GaussianCopulaSynthesizer(metadata)
    sample_data = pd.DataFrame({
        'transaction_id': [1, 2, 3, 4], 'account': ['acc_1', 'acc_2', 'acc_1', 'acc_3'],
        'amount': [100.50, 25.00, 5000.00, 150.75], 'currency': ['USD', 'EUR', 'USD', 'GBP'],
        'timestamp': pd.to_datetime(['2023-01-01 12:00', '2023-01-01 13:00', '2023-01-02 08:00', '2023-01-02 09:00']),
        'is_fraud': [False, False, True, False]
    })
    synthesizer.fit(sample_data)
    synthetic_data = synthesizer.sample(num_rows=20000)
    print(f"Successfully generated {len(synthetic_data)} transactions.")

    # --- Step 2: Feature Engineering ---
    print("\n[2/5] Performing feature engineering...")
    synthetic_data = synthetic_data.sort_values(['account', 'timestamp'])
    synthetic_data['hour_of_day'] = synthetic_data['timestamp'].dt.hour
    synthetic_data['day_of_week'] = synthetic_data['timestamp'].dt.dayofweek
    synthetic_data['avg_amount_per_account'] = synthetic_data.groupby('account')['amount'].transform('mean')
    synthetic_data['amount_deviation'] = synthetic_data['amount'] - synthetic_data['avg_amount_per_account']
    synthetic_data['time_since_last_txn'] = synthetic_data.groupby('account')['timestamp'].diff().dt.total_seconds().fillna(0)
    features = ['amount', 'currency', 'hour_of_day', 'day_of_week', 'avg_amount_per_account', 'amount_deviation', 'time_since_last_txn']
    target = 'is_fraud'
    X = synthetic_data[features]
    y = synthetic_data[target]

    # --- Step 3: Preprocessing ---
    print("\n[3/5] Preprocessing data...")
    categorical_features = ['currency']
    preprocessor = ColumnTransformer(transformers=[('cat', OneHotEncoder(handle_unknown='ignore'), categorical_features)], remainder='passthrough')
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)

    # --- Step 4: Hyperparameter Tuning ---
    print("\n[4/5] Training and tuning the model... (This may take a minute)")
    scale_pos_weight = y_train.value_counts()[0] / y_train.value_counts()[1]
    pipeline = Pipeline(steps=[
        ('preprocessor', preprocessor),
        ('classifier', xgb.XGBClassifier(objective='binary:logistic', eval_metric='logloss', use_label_encoder=False, scale_pos_weight=scale_pos_weight))
    ])
    param_grid = {'classifier__n_estimators': [100, 200], 'classifier__max_depth': [3, 5], 'classifier__learning_rate': [0.1]}
    grid_search = GridSearchCV(pipeline, param_grid, cv=3, n_jobs=-1, verbose=0, scoring='f1')
    grid_search.fit(X_train, y_train)
    best_model = grid_search.best_estimator_
    print("Model training complete. Best parameters found: ", grid_search.best_params_)

    # --- Step 5: Save the Model ---
    print("\n[5/5] Saving model and preprocessor files...")

    if not os.path.exists('app'):
        os.makedirs('app')
    joblib.dump(best_model.named_steps['classifier'], 'app/xgboost_model.pkl')
    joblib.dump(best_model.named_steps['preprocessor'], 'app/preprocessor.pkl')


    y_pred = best_model.predict(X_test)
    print("\n---- Model Report ----")
    print(classification_report(y_test, y_pred))
    print("Model training complete. New .pkl files saved in the 'app' directory.")


if __name__ == "__main__":
    train()