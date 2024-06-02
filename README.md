Quick books Integration with existing system
- Runs with command php -S localhost:3000. 
- Scripts can be tested from postman.
- Create a .env file
- variables for the .env file are in the .env.example file(fill in with the necessary variables)
- first login is manual in order to obtain an access token to store and be refreshed continuosly.
- Create database in mysql, Below is a sample mysql query for the tokens table.
CREATE TABLE tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    access_token_expiry TIMESTAMP NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-Get your first token that will be stored in the database using oauth.php (POST REQUEST)
-To get a token you will need to provide an authentication code in the params of you request.
-An authentication code can be acquired from the quickboojs playground.(This will be needed once)
-Use to createInvoice.php to create an invoice using the request body entioned below 
- Below is a sample request body to create an invoice (POST REQUEST)
- {
    "customerRef": {
        "value": "Test Test Client"
    },
    "TxnDate": "2024-06-02",
    "DueDate": "2024-06-15",
    "Line": [
        {
            "SalesItemLineDetail": {
                "ItemRef": {
                    "name": "Item 1"
                },
                "Qty": 2,
                "UnitPrice": 40.00
            }
        },
        {
            "SalesItemLineDetail": {
                "ItemRef": {
                    "name": "Item 2"
                },
                "Qty": 1,
                "UnitPrice": 25.00
            }
        }
    ]
}
