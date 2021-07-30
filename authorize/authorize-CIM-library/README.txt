Here are issues that have existed since the CIM API was open to the public.

This issues still exist and are either undocumented in the manual or unresolved.
I have repeatedly asked developer support to resolve it, but my attempts were fruitless.
You should know about this and help to raise these issues too.
However, these issue will not prevent you from using the CIM API.
It just makes it more difficult for people like me who try to provide you with software.



1)
In validateCustomerPaymentProfileRequest(), customerShippingAddressId() is
used in place of customerAddressId().
The Authorize.net manual is still incorrect on this.

2)
The manual states there is supposed to be a
"customerAddressId" in the "transaction" element of 
createCustomerProfileTransactionRequest(), however,it is supposed to be
"customerShippingAddressId" instead.

3)
The nested order of the elements within the "<bankAccount>" element
for createCustomerProfileRequest(), createCustomerPaymentProfileRequest() and
updateCustomerPaymentProfileRequest() is incorrect in the manual.

These elements are:

accountType
routingNumber
accountNumber
nameOnAccount
echeckType
bankName

However, this has been fixed in the php class.


4)
When updating a profile using updateCustomerPaymentProfileRequest(),
payment details are required such as creditCard or bankAccount(echeck).

Also, for certain elements, the manual states that
"Sensitive information that is not being updated can be masked.".
However, this is not true. I was told by Authorize.net that masking means to leave the 
value blank for which it will not update certain info if desired, 
but instead if you leave it blank, it will delete the information for a particular element
in the CIM.

5)
Undocumented Information:
When using updateCustomerShippingAddressRequest() and you don't specify an
optional element as shown in the manual, then you use getCustomerShippingAddressRequest() 
to retrieve the shipping info, the optional element you originally left out 
will not be returned in the response.
