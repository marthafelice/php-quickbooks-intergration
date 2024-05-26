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

- Below is a sample request body to create an invoice
{
    "customerRef": {
        "value": "Test"
    },
    "TxnDate": "2024-05-01",
    "DueDate": "2024-05-15",
    "Line": [
        {
            "SalesItemLineDetail": {
                "ItemRef": {
                    "name": "Item 1"
                },
                "Qty": 2,
                "UnitPrice": 50.00
            }
        },
        {
            "SalesItemLineDetail": {
                "ItemRef": {
                    "name": "Item 2"
                },
                "Qty": 1,
                "UnitPrice": 75.00
            }
        }
    ]
}
